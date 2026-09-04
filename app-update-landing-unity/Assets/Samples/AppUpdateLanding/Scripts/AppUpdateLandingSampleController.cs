using System;
using System.Threading;
using Harapeco.AppUpdateLanding;
using UnityEngine;
using UnityEngine.UI;

namespace Harapeco.AppUpdateLandingSample
{
    public sealed class AppUpdateLandingSampleController : MonoBehaviour
    {
        private readonly AppUpdateLandingJapaneseFormatter formatter =
            AppUpdateLandingJapaneseFormatter.Default;

        [SerializeField]
        private Text apiUrlText;

        [SerializeField]
        private Text testStateText;

        [SerializeField]
        private Text stateText;

        [SerializeField]
        private Text messageText;

        [SerializeField]
        private Text detailsText;

        [SerializeField]
        private Text errorText;

        [SerializeField]
        private Button refreshButton;

        [SerializeField]
        private Button webButton;

        private CancellationTokenSource cancellation;
        private AppUpdateLandingClient client;
        private AppUpdateLandingStatus status;
        private bool isLoading;

        private void Start()
        {
            if (!HasRequiredReferences())
            {
                Debug.LogError(
                    "[App Update Landing Sample] Required UI references are missing.",
                    this);
                enabled = false;
                return;
            }

            refreshButton.onClick.AddListener(Refresh);
            webButton.onClick.AddListener(OpenWebPage);
            cancellation = new CancellationTokenSource();
            Render();
            Refresh();
        }

        private void OnDestroy()
        {
            if (refreshButton != null)
            {
                refreshButton.onClick.RemoveListener(Refresh);
            }

            if (webButton != null)
            {
                webButton.onClick.RemoveListener(OpenWebPage);
            }

            if (cancellation == null)
            {
                return;
            }

            cancellation.Cancel();
            cancellation.Dispose();
            cancellation = null;
        }

        private async void Refresh()
        {
            if (isLoading || cancellation == null)
            {
                return;
            }

            isLoading = true;
            status = null;
            errorText.text = string.Empty;
            Render();

            try
            {
                client = new AppUpdateLandingClient();
                status = await client.RefreshAsync(cancellation.Token);
            }
            catch (OperationCanceledException)
            {
            }
            catch (AppUpdateLandingException exception)
            {
                errorText.text = exception.Code + ": " + exception.Message;
            }
            catch (Exception exception)
            {
                errorText.text = exception.Message;
            }
            finally
            {
                isLoading = false;
                if (this != null)
                {
                    Render();
                }
            }
        }

        private void Render()
        {
            var settings = AppUpdateLandingSettings.Current;
            apiUrlText.text = "API URL: " + EmptyFallback(settings.ApiUrl);
            testStateText.text = "Test State: " + TestStateLabel(settings.TestState);
            refreshButton.interactable = !isLoading;

            if (isLoading)
            {
                stateText.text = "取得中";
                messageText.text = "APIからイベント情報を取得しています…";
                detailsText.text = string.Empty;
                webButton.gameObject.SetActive(false);
                return;
            }

            if (status == null)
            {
                stateText.text = string.IsNullOrEmpty(errorText.text) ? "未取得" : "取得失敗";
                messageText.text = string.Empty;
                detailsText.text = string.Empty;
                webButton.gameObject.SetActive(false);
                return;
            }

            var display = formatter.Format(status);
            stateText.text = StateLabel(status.State);
            messageText.text = display.Text;
            detailsText.text =
                "State: " + status.State + "\n"
                + "Release Version: " + status.ReleaseVersion + "\n"
                + "Platform: " + status.Platform + "\n"
                + "Fetched At: " + status.FetchedAt.ToLocalTime().ToString("yyyy-MM-dd HH:mm:ss");
            webButton.gameObject.SetActive(CanOpenWebPage(status));
        }

        private void OpenWebPage()
        {
            if (!CanOpenWebPage(status) || client == null)
            {
                return;
            }

            try
            {
                client.OpenCurrentPage();
            }
            catch (Exception exception)
            {
                errorText.text = exception.Message;
            }
        }

        private bool HasRequiredReferences()
        {
            return apiUrlText != null
                   && testStateText != null
                   && stateText != null
                   && messageText != null
                   && detailsText != null
                   && errorText != null
                   && refreshButton != null
                   && webButton != null;
        }

        private static bool CanOpenWebPage(AppUpdateLandingStatus current)
        {
            return current != null
                   && (current.State == AppUpdateLandingEventState.Upcoming
                       || current.State == AppUpdateLandingEventState.Active);
        }

        private static string StateLabel(AppUpdateLandingEventState state)
        {
            switch (state)
            {
                case AppUpdateLandingEventState.Disabled:
                    return "イベント無し";
                case AppUpdateLandingEventState.Upcoming:
                    return "イベント前";
                case AppUpdateLandingEventState.WaitingForRelease:
                    return "アップデート待ち";
                case AppUpdateLandingEventState.Active:
                    return "イベント中";
                case AppUpdateLandingEventState.Ended:
                    return "イベント後";
                default:
                    return state.ToString();
            }
        }

        private static string TestStateLabel(AppUpdateLandingTestState testState)
        {
            switch (testState)
            {
                case AppUpdateLandingTestState.NoEvent:
                    return "イベント無し";
                case AppUpdateLandingTestState.Upcoming:
                    return "イベント前";
                case AppUpdateLandingTestState.WaitingForRelease:
                    return "アップデート待ち";
                case AppUpdateLandingTestState.Active:
                    return "イベント中";
                case AppUpdateLandingTestState.Ended:
                    return "イベント後";
                default:
                    return "API Response";
            }
        }

        private static string EmptyFallback(string value)
        {
            return string.IsNullOrEmpty(value) ? "(未設定)" : value;
        }
    }
}
