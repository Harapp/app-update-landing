using System;

namespace Harapeco.AppUpdateLanding
{
    public enum AppUpdateLandingEventPhase
    {
        Upcoming,
        Active,
        Ended
    }

    public enum AppUpdateLandingEventState
    {
        Disabled,
        Upcoming,
        WaitingForRelease,
        Active,
        Ended
    }

    public enum AppUpdateLandingPlatform
    {
        Ios,
        Android,
        Pc
    }

    public sealed class AppUpdateLandingStatus
    {
        internal AppUpdateLandingStatus(
            int schemaVersion,
            string releaseVersion,
            string pageUrl,
            bool enabled,
            DateTimeOffset? startAt,
            DateTimeOffset? endAt,
            AppUpdateLandingEventPhase phase,
            AppUpdateLandingPlatform platform,
            bool released,
            DateTimeOffset fetchedAt)
        {
            SchemaVersion = schemaVersion;
            ReleaseVersion = releaseVersion;
            PageUrl = pageUrl;
            Enabled = enabled;
            StartAt = startAt;
            EndAt = endAt;
            Phase = phase;
            Platform = platform;
            Released = released;
            FetchedAt = fetchedAt;
        }

        public int SchemaVersion { get; }

        public string ReleaseVersion { get; }

        public string PageUrl { get; }

        public bool Enabled { get; }

        public DateTimeOffset? StartAt { get; }

        public DateTimeOffset? EndAt { get; }

        public AppUpdateLandingEventPhase Phase { get; }

        public AppUpdateLandingPlatform Platform { get; }

        public bool Released { get; }

        public DateTimeOffset FetchedAt { get; }

        public AppUpdateLandingEventState State
        {
            get
            {
                if (!Enabled)
                {
                    return AppUpdateLandingEventState.Disabled;
                }

                if (Phase == AppUpdateLandingEventPhase.Ended)
                {
                    return AppUpdateLandingEventState.Ended;
                }

                if (Phase == AppUpdateLandingEventPhase.Upcoming)
                {
                    return AppUpdateLandingEventState.Upcoming;
                }

                return Released
                    ? AppUpdateLandingEventState.Active
                    : AppUpdateLandingEventState.WaitingForRelease;
            }
        }
    }
}
