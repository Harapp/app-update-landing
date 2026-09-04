using System;
using System.Threading;
using System.Threading.Tasks;

namespace Harapeco.AppUpdateLanding
{
    public enum AppUpdateLandingDialogResult
    {
        Dismissed,
        OpenPage,
        Cancelled
    }

    public sealed class AppUpdateLandingDialogRequest
    {
        internal AppUpdateLandingDialogRequest(
            AppUpdateLandingStatus status,
            AppUpdateLandingDisplay display,
            string pageUrl)
        {
            Status = status;
            Display = display;
            PageUrl = pageUrl;
        }

        public AppUpdateLandingStatus Status { get; }

        public AppUpdateLandingDisplay Display { get; }

        public string PageUrl { get; }
    }

    public interface IAppUpdateLandingDialogPresenter
    {
        Task<AppUpdateLandingDialogResult> PresentAsync(
            AppUpdateLandingDialogRequest request,
            CancellationToken cancellationToken);
    }

    [Serializable]
    public sealed class AppUpdateLandingDismissDialogPresenter : IAppUpdateLandingDialogPresenter
    {
        public Task<AppUpdateLandingDialogResult> PresentAsync(
            AppUpdateLandingDialogRequest request,
            CancellationToken cancellationToken)
        {
            cancellationToken.ThrowIfCancellationRequested();
            return Task.FromResult(AppUpdateLandingDialogResult.Dismissed);
        }
    }
}
