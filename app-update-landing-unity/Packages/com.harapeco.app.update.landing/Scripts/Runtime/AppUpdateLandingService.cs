using System;
using System.Threading;
using System.Threading.Tasks;
using UnityEngine;

namespace Harapeco.AppUpdateLanding
{
    public sealed class AppUpdateLandingService : MonoBehaviour
    {
        private const int FailureRetryDelaySeconds = 60;
        private const int NoScheduledProcessDelayMilliseconds = int.MaxValue;

        private static AppUpdateLandingService instance;

        private readonly SemaphoreSlim wakeSignal = new SemaphoreSlim(0, 1);
        private CancellationTokenSource lifetimeCancellation;
        private AppUpdateLandingClient client;
        private bool isPaused;

        public static AppUpdateLandingService Instance => instance;

        public event Action<AppUpdateLandingStatus> StatusUpdated;

        public AppUpdateLandingStatus CurrentStatus => client?.CurrentStatus;

        public AppUpdateLandingClient Client => RequireClient();

        [RuntimeInitializeOnLoadMethod(RuntimeInitializeLoadType.BeforeSceneLoad)]
        private static void InitializeOnLoad()
        {
            if (instance != null)
            {
                return;
            }

            var gameObject = new GameObject(nameof(AppUpdateLandingService));
            gameObject.AddComponent<AppUpdateLandingService>();
        }

        private void Awake()
        {
            if (instance != null && instance != this)
            {
                Destroy(gameObject);
                return;
            }

            instance = this;
            DontDestroyOnLoad(gameObject);
            lifetimeCancellation = new CancellationTokenSource();

            try
            {
                client = new AppUpdateLandingClient();
                client.StatusUpdated += HandleStatusUpdated;
                _ = RunSchedulerAsync(lifetimeCancellation.Token);
            }
            catch (Exception exception)
            {
                Debug.LogException(exception, this);
                enabled = false;
            }
        }

        private void OnApplicationPause(bool pauseStatus)
        {
            isPaused = pauseStatus;
            if (!pauseStatus)
            {
                WakeScheduler();
            }
        }

        private void OnApplicationQuit()
        {
            isPaused = true;
            lifetimeCancellation?.Cancel();
        }

        private void OnDestroy()
        {
            if (client != null)
            {
                client.StatusUpdated -= HandleStatusUpdated;
            }

            if (ReferenceEquals(instance, this))
            {
                instance = null;
            }

            if (lifetimeCancellation != null)
            {
                lifetimeCancellation.Cancel();
                lifetimeCancellation.Dispose();
                lifetimeCancellation = null;
            }

            wakeSignal.Dispose();
        }

        public async Task<AppUpdateLandingStatus> RefreshAsync(
            CancellationToken cancellationToken = default)
        {
            try
            {
                return await RequireClient().RefreshAsync(cancellationToken);
            }
            finally
            {
                WakeScheduler();
            }
        }

        public async Task<AppUpdateLandingStatus> ForceRefreshAsync(
            CancellationToken cancellationToken = default)
        {
            try
            {
                return await RequireClient().ForceRefreshAsync(cancellationToken);
            }
            finally
            {
                WakeScheduler();
            }
        }

        private async Task RunSchedulerAsync(CancellationToken cancellationToken)
        {
            while (!cancellationToken.IsCancellationRequested)
            {
                try
                {
                    if (isPaused)
                    {
                        await wakeSignal.WaitAsync(cancellationToken);
                        continue;
                    }

                    await client.RefreshAsync(cancellationToken);
                    await WaitUntilAsync(
                        client.GetNextProcessAt(DateTimeOffset.UtcNow),
                        cancellationToken);
                }
                catch (OperationCanceledException)
                {
                    return;
                }
                catch (Exception exception)
                {
                    Debug.LogException(exception, this);
                    await WaitUntilAsync(
                        DateTimeOffset.UtcNow.AddSeconds(FailureRetryDelaySeconds),
                        cancellationToken);
                }
            }
        }

        private async Task WaitUntilAsync(
            DateTimeOffset? scheduledAt,
            CancellationToken cancellationToken)
        {
            if (scheduledAt == null)
            {
                await wakeSignal.WaitAsync(cancellationToken);
                return;
            }

            var delay = scheduledAt.Value - DateTimeOffset.UtcNow;
            var delayMilliseconds = delay.TotalMilliseconds <= 0
                ? 0
                : delay.TotalMilliseconds >= int.MaxValue
                    ? NoScheduledProcessDelayMilliseconds
                    : (int)Math.Ceiling(delay.TotalMilliseconds);
            var delayTask = Task.Delay(delayMilliseconds, cancellationToken);
            var wakeTask = wakeSignal.WaitAsync(cancellationToken);
            var completedTask = await Task.WhenAny(delayTask, wakeTask);
            if (ReferenceEquals(completedTask, wakeTask))
            {
                await wakeTask;
            }
            else
            {
                await delayTask;
            }
        }

        private void WakeScheduler()
        {
            if (lifetimeCancellation == null)
            {
                return;
            }

            if (wakeSignal.CurrentCount == 0)
            {
                wakeSignal.Release();
            }
        }

        private void HandleStatusUpdated(AppUpdateLandingStatus status)
        {
            StatusUpdated?.Invoke(status);
        }

        private AppUpdateLandingClient RequireClient()
        {
            if (client == null)
            {
                throw new InvalidOperationException(
                    "AppUpdateLandingService has not been initialized.");
            }

            return client;
        }
    }
}
