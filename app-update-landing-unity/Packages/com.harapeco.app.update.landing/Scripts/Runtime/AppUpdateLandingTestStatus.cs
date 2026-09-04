using System;

namespace Harapeco.AppUpdateLanding
{
    internal static class AppUpdateLandingTestStatus
    {
        public static AppUpdateLandingStatus Apply(
            AppUpdateLandingStatus source,
            AppUpdateLandingTestState testState,
            DateTimeOffset now)
        {
            if (source == null)
            {
                throw new ArgumentNullException(nameof(source));
            }

            if (testState == AppUpdateLandingTestState.ApiResponse)
            {
                return source;
            }

            var enabled = true;
            var released = true;
            DateTimeOffset? startAt;
            DateTimeOffset? endAt;
            AppUpdateLandingEventPhase phase;

            switch (testState)
            {
                case AppUpdateLandingTestState.NoEvent:
                    enabled = false;
                    startAt = null;
                    endAt = null;
                    phase = AppUpdateLandingEventPhase.Ended;
                    break;
                case AppUpdateLandingTestState.Upcoming:
                    startAt = now.AddDays(3);
                    endAt = now.AddDays(10);
                    phase = AppUpdateLandingEventPhase.Upcoming;
                    break;
                case AppUpdateLandingTestState.WaitingForRelease:
                    startAt = now.AddDays(-1);
                    endAt = now.AddDays(6);
                    phase = AppUpdateLandingEventPhase.Active;
                    released = false;
                    break;
                case AppUpdateLandingTestState.Active:
                    startAt = now.AddDays(-3);
                    endAt = now.AddDays(7);
                    phase = AppUpdateLandingEventPhase.Active;
                    break;
                case AppUpdateLandingTestState.Ended:
                    startAt = now.AddDays(-10);
                    endAt = now.AddDays(-1);
                    phase = AppUpdateLandingEventPhase.Ended;
                    break;
                default:
                    throw new ArgumentOutOfRangeException(nameof(testState), testState, null);
            }

            return new AppUpdateLandingStatus(
                source.SchemaVersion,
                source.ReleaseVersion,
                source.PageUrl,
                enabled,
                startAt,
                endAt,
                phase,
                source.Platform,
                released,
                now);
        }
    }
}
