using System;
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
        private readonly string apiUrl;
        private readonly AppUpdateLandingEnvironment environment;
        private readonly IAppUpdateLandingUrlOpener urlOpener;
        private readonly int timeoutSeconds;
        private readonly AppUpdateLandingTestState testState;

        public AppUpdateLandingClient()
            : this(AppUpdateLandingSettings.LoadFromResources())
        {
        }

        public AppUpdateLandingClient(
            AppUpdateLandingSettings settings,
            AppUpdateLandingEnvironment environment = null,
            IAppUpdateLandingUrlOpener urlOpener = null,
            int timeoutSeconds = 15)
            : this(
                RequireApiUrl(settings),
                environment,
                urlOpener,
                timeoutSeconds,
                settings.TestState)
        {
        }

        public AppUpdateLandingClient(
            string apiUrl,
            AppUpdateLandingEnvironment environment = null,
            IAppUpdateLandingUrlOpener urlOpener = null,
            int timeoutSeconds = 15)
            : this(
                apiUrl,
                environment,
                urlOpener,
                timeoutSeconds,
                AppUpdateLandingTestState.ApiResponse)
        {
        }

        private AppUpdateLandingClient(
            string apiUrl,
            AppUpdateLandingEnvironment environment,
            IAppUpdateLandingUrlOpener urlOpener,
            int timeoutSeconds,
            AppUpdateLandingTestState testState)
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
            this.testState = testState;
        }

        public event Action<AppUpdateLandingStatus> StatusUpdated;

        public AppUpdateLandingStatus CurrentStatus { get; private set; }

        public async Task<AppUpdateLandingStatus> RefreshAsync(
            CancellationToken cancellationToken = default)
        {
            cancellationToken.ThrowIfCancellationRequested();
            var json = await GetJsonAsync(cancellationToken);
            var fetchedAt = DateTimeOffset.UtcNow;
            var status = AppUpdateLandingResponseParser.Parse(
                json,
                environment.Platform,
                fetchedAt);
#if UNITY_EDITOR
            status = AppUpdateLandingTestStatus.Apply(status, testState, fetchedAt);
#endif
            CurrentStatus = status;
            StatusUpdated?.Invoke(status);
            return status;
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
