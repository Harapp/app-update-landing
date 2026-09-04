using System;
using System.Collections.Generic;
using System.Globalization;
using System.Threading;
using System.Threading.Tasks;
using UnityEngine;
using UnityEngine.Networking;

namespace Harapeco.AppUpdateLanding
{
    public interface IAppUpdateLandingUrlOpener
    {
        void Open(string url);
    }

    public sealed class AppUpdateLandingUnityUrlOpener : IAppUpdateLandingUrlOpener
    {
        public void Open(string url)
        {
            Application.OpenURL(url);
        }
    }

    public sealed class AppUpdateLandingClient
    {
        private const string CacheKeyPrefix = "Harapeco.AppUpdateLanding.v1.";

        private static readonly object SharedStatesLock = new object();
        private static readonly Dictionary<string, SharedState> SharedStates =
            new Dictionary<string, SharedState>(StringComparer.Ordinal);

        private readonly string apiUrl;
        private readonly AppUpdateLandingEnvironment environment;
        private readonly IAppUpdateLandingUrlOpener urlOpener;
        private readonly int timeoutSeconds;
        private readonly AppUpdateLandingSettings settings;
        private readonly SharedState sharedState;

        public AppUpdateLandingClient()
            : this(AppUpdateLandingSettings.LoadFromResources())
        {
        }

        public AppUpdateLandingClient(
            AppUpdateLandingSettings settings,
            AppUpdateLandingEnvironment environment = null,
            IAppUpdateLandingUrlOpener urlOpener = null,
            int timeoutSeconds = 15)
            : this(RequireApiUrl(settings), environment, urlOpener, timeoutSeconds, settings)
        {
        }

        public AppUpdateLandingClient(
            string apiUrl,
            AppUpdateLandingEnvironment environment = null,
            IAppUpdateLandingUrlOpener urlOpener = null,
            int timeoutSeconds = 15)
            : this(apiUrl, environment, urlOpener, timeoutSeconds, null)
        {
        }

        private AppUpdateLandingClient(
            string apiUrl,
            AppUpdateLandingEnvironment environment,
            IAppUpdateLandingUrlOpener urlOpener,
            int timeoutSeconds,
            AppUpdateLandingSettings settings)
        {
            if (!AppUpdateLandingUrlBuilder.TryCreateAllowedWebUri(apiUrl, out var uri))
            {
                throw new AppUpdateLandingException(
                    "invalid_api_url",
                    "apiUrl must be an absolute HTTPS URL. HTTP is allowed only for loopback development URLs.");
            }

            if (timeoutSeconds <= 0)
            {
                throw new ArgumentOutOfRangeException(nameof(timeoutSeconds));
            }

            this.apiUrl = uri.AbsoluteUri;
            this.environment = environment ?? AppUpdateLandingEnvironment.FromUnity();
            this.urlOpener = urlOpener ?? new AppUpdateLandingUnityUrlOpener();
            this.timeoutSeconds = timeoutSeconds;
            this.settings = settings;
            sharedState = GetSharedState(
                BuildCacheKey(this.apiUrl, this.environment.Platform),
                this.environment.Platform);
        }

        public event Action<AppUpdateLandingStatus> StatusUpdated;

        public AppUpdateLandingStatus CurrentStatus { get; private set; }

        public async Task<AppUpdateLandingStatus> RefreshAsync(
            CancellationToken cancellationToken = default)
        {
            cancellationToken.ThrowIfCancellationRequested();
            var now = DateTimeOffset.UtcNow;
            Task<AppUpdateLandingStatus> refreshTask;
            AppUpdateLandingStatus cachedStatus = null;
            lock (sharedState.SyncRoot)
            {
                EnsureLoaded(sharedState);
                if (sharedState.Status != null
                    && !ShouldFetch(sharedState, sharedState.Status, now))
                {
                    cachedStatus = sharedState.Status;
                    refreshTask = null;
                }
                else
                {
                    refreshTask = sharedState.RefreshTask;
                    if (refreshTask == null)
                    {
                        refreshTask = RefreshAndPersistAsync(sharedState, cancellationToken);
                        sharedState.RefreshTask = refreshTask;
                    }
                }
            }

            if (cachedStatus != null)
            {
                CurrentStatus = cachedStatus;
                StatusUpdated?.Invoke(CurrentStatus);
                return CurrentStatus;
            }

            try
            {
                var status = await refreshTask;
                CurrentStatus = status;
                StatusUpdated?.Invoke(status);
                return status;
            }
            finally
            {
                lock (sharedState.SyncRoot)
                {
                    if (ReferenceEquals(sharedState.RefreshTask, refreshTask))
                    {
                        sharedState.RefreshTask = null;
                    }
                }
            }
        }

        public string BuildCurrentPageUrl()
        {
            if (CurrentStatus == null)
            {
                throw new InvalidOperationException("RefreshAsync must complete before opening the page.");
            }

            return AppUpdateLandingUrlBuilder.Build(CurrentStatus.PageUrl, environment);
        }

        public string OpenCurrentPage()
        {
            var pageUrl = BuildCurrentPageUrl();
            urlOpener.Open(pageUrl);
            return pageUrl;
        }

        public async Task<AppUpdateLandingDialogResult> PresentDetailsAsync(
            IAppUpdateLandingDialogPresenter presenter,
            CancellationToken cancellationToken = default)
        {
            return await PresentDetailsAsync(
                presenter,
                AppUpdateLandingJapaneseFormatter.Default,
                cancellationToken);
        }

        public async Task<AppUpdateLandingDialogResult> PresentDetailsAsync(
            IAppUpdateLandingDialogPresenter presenter,
            IAppUpdateLandingStatusFormatter formatter,
            CancellationToken cancellationToken = default)
        {
            if (presenter == null)
            {
                throw new ArgumentNullException(nameof(presenter));
            }
            if (formatter == null)
            {
                throw new ArgumentNullException(nameof(formatter));
            }
            if (CurrentStatus == null)
            {
                throw new InvalidOperationException("RefreshAsync must complete before presenting details.");
            }

            cancellationToken.ThrowIfCancellationRequested();
            var pageUrl = BuildCurrentPageUrl();
            AppUpdateLandingDialogResult result;
            try
            {
                result = await presenter.PresentAsync(
                    new AppUpdateLandingDialogRequest(
                        CurrentStatus,
                        formatter.Format(CurrentStatus),
                        pageUrl),
                    cancellationToken);
            }
            catch (OperationCanceledException)
            {
                throw;
            }
            catch (Exception exception)
            {
                throw new AppUpdateLandingException(
                    "dialog_presenter_failed",
                    "The App Update Landing dialog presenter failed.",
                    exception);
            }

            cancellationToken.ThrowIfCancellationRequested();
            if (result == AppUpdateLandingDialogResult.OpenPage)
            {
                urlOpener.Open(pageUrl);
            }

            return result;
        }

        private static string RequireApiUrl(AppUpdateLandingSettings settings)
        {
            var validation = AppUpdateLandingSettings.Validate(settings);
            if (validation.IsValid)
            {
                return settings.ApiUrl;
            }

            string code;
            switch (validation.Code)
            {
                case AppUpdateLandingSettingsValidationCode.SettingsMissing:
                    code = "settings_not_found";
                    break;
                case AppUpdateLandingSettingsValidationCode.ApiUrlMissing:
                    code = "api_url_required";
                    break;
                default:
                    code = "invalid_api_url";
                    break;
            }

            throw new AppUpdateLandingException(code, validation.Message);
        }

        private async Task<AppUpdateLandingStatus> RefreshAndPersistAsync(
            SharedState state,
            CancellationToken cancellationToken)
        {
            var json = await GetJsonAsync(cancellationToken);
            var fetchedAt = DateTimeOffset.UtcNow;
            var status = AppUpdateLandingResponseParser.Parse(
                json,
                environment.Platform,
                fetchedAt);

            lock (state.SyncRoot)
            {
                var hadPreviousFetch = state.HasLastFetchedAt;
                state.Status = status;
                state.LastFetchedAt = fetchedAt;
                state.HasLastFetchedAt = true;
                if (hadPreviousFetch)
                {
                    state.BackoffAttempt = Math.Min(
                        state.BackoffAttempt + 1,
                        int.MaxValue - 1);
                }
                state.Loaded = true;
                Save(state, json);
            }

            return status;
        }

        private bool ShouldFetch(
            SharedState state,
            AppUpdateLandingStatus status,
            DateTimeOffset now)
        {
            var currentState = status.GetState(now);
            if (currentState == AppUpdateLandingEventState.Upcoming
                || currentState == AppUpdateLandingEventState.WaitingForRelease
                || currentState == AppUpdateLandingEventState.Active)
            {
                return false;
            }

            if (!state.HasLastFetchedAt)
            {
                return true;
            }

            return now >= state.LastFetchedAt.AddDays(GetBackoffDays(state.BackoffAttempt));
        }

        private float GetBackoffDays(int attempt)
        {
            return settings == null
                ? GetDefaultBackoffDays(attempt)
                : settings.GetBackoffDays(attempt);
        }

        private static float GetDefaultBackoffDays(int attempt)
        {
            return attempt <= 0 ? 1f : attempt == 1 ? 2f : 3f;
        }

        private static SharedState GetSharedState(
            string key,
            AppUpdateLandingPlatform platform)
        {
            lock (SharedStatesLock)
            {
                if (!SharedStates.TryGetValue(key, out var state))
                {
                    state = new SharedState(key, platform);
                    SharedStates.Add(key, state);
                }

                return state;
            }
        }

        private static void EnsureLoaded(SharedState state)
        {
            if (state.Loaded)
            {
                return;
            }

            state.Loaded = true;
            if (!PlayerPrefs.HasKey(state.StatusKey))
            {
                return;
            }

            try
            {
                var fetchedAt = ReadDateTime(
                    PlayerPrefs.GetString(state.LastFetchedAtKey, string.Empty));
                if (!fetchedAt.HasValue)
                {
                    return;
                }

                state.Status = AppUpdateLandingResponseParser.Parse(
                    PlayerPrefs.GetString(state.StatusKey),
                    state.Platform,
                    fetchedAt.Value);
                state.LastFetchedAt = fetchedAt.Value;
                state.HasLastFetchedAt = true;
                state.BackoffAttempt = Math.Max(
                    0,
                    PlayerPrefs.GetInt(state.BackoffAttemptKey, 0));
            }
            catch (Exception)
            {
                state.Status = null;
                state.HasLastFetchedAt = false;
                state.BackoffAttempt = 0;
            }
        }

        private static void Save(SharedState state, string json)
        {
            PlayerPrefs.SetString(state.StatusKey, json);
            PlayerPrefs.SetString(
                state.LastFetchedAtKey,
                state.LastFetchedAt.UtcDateTime.Ticks.ToString(CultureInfo.InvariantCulture));
            PlayerPrefs.SetInt(state.BackoffAttemptKey, state.BackoffAttempt);
            PlayerPrefs.Save();
        }

        private static DateTimeOffset? ReadDateTime(string value)
        {
            if (!long.TryParse(value, NumberStyles.Integer, CultureInfo.InvariantCulture, out var ticks)
                || ticks <= 0)
            {
                return null;
            }

            try
            {
                return new DateTimeOffset(new DateTime(ticks, DateTimeKind.Utc));
            }
            catch (ArgumentOutOfRangeException)
            {
                return null;
            }
        }

        private static string BuildCacheKey(
            string apiUrl,
            AppUpdateLandingPlatform platform)
        {
            unchecked
            {
                var hash = 2166136261u;
                var value = apiUrl + "|" + AppUpdateLandingEnvironment.PlatformName(platform);
                foreach (var character in value)
                {
                    hash ^= character;
                    hash *= 16777619u;
                }

                return CacheKeyPrefix + hash.ToString("X8", CultureInfo.InvariantCulture);
            }
        }

        private sealed class SharedState
        {
            public SharedState(string key, AppUpdateLandingPlatform platform)
            {
                SyncRoot = new object();
                Platform = platform;
                StatusKey = key + ".status";
                LastFetchedAtKey = key + ".lastFetchedAt";
                BackoffAttemptKey = key + ".backoffAttempt";
            }

            public readonly object SyncRoot;
            public readonly AppUpdateLandingPlatform Platform;
            public readonly string StatusKey;
            public readonly string LastFetchedAtKey;
            public readonly string BackoffAttemptKey;
            public AppUpdateLandingStatus Status;
            public DateTimeOffset LastFetchedAt;
            public bool HasLastFetchedAt;
            public int BackoffAttempt;
            public bool Loaded;
            public Task<AppUpdateLandingStatus> RefreshTask;
        }

        private async Task<string> GetJsonAsync(CancellationToken cancellationToken)
        {
            using (var request = UnityWebRequest.Get(apiUrl))
            {
                request.timeout = timeoutSeconds;
                request.SetRequestHeader("Accept", "application/json");
                var operation = request.SendWebRequest();
                try
                {
                    while (!operation.isDone)
                    {
                        cancellationToken.ThrowIfCancellationRequested();
                        await Task.Yield();
                    }
                }
                catch (OperationCanceledException)
                {
                    request.Abort();
                    throw;
                }

                if (request.result != UnityWebRequest.Result.Success)
                {
                    throw new AppUpdateLandingException(
                        request.responseCode > 0 ? "http_error" : "request_failed",
                        request.responseCode > 0
                            ? "The App Update Landing API returned an error response."
                            : "The App Update Landing API request failed.",
                        request.responseCode);
                }

                return request.downloadHandler.text;
            }
        }
    }
}
