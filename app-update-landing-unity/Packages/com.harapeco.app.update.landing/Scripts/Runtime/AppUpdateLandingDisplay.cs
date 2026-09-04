using System;
using System.Collections.Generic;
using System.Globalization;

namespace Harapeco.AppUpdateLanding
{
    public sealed class AppUpdateLandingDisplay
    {
        internal AppUpdateLandingDisplay(
            string statusLabel,
            string dateRange,
            string timingText,
            string text)
        {
            StatusLabel = statusLabel;
            DateRange = dateRange;
            TimingText = timingText;
            Text = text;
        }

        public string StatusLabel { get; }

        public string DateRange { get; }

        public string TimingText { get; }

        public string Text { get; }
    }

    public interface IAppUpdateLandingStatusFormatter
    {
        AppUpdateLandingDisplay Format(AppUpdateLandingStatus status);
    }

    public sealed class AppUpdateLandingJapaneseFormatter : IAppUpdateLandingStatusFormatter
    {
        public static readonly AppUpdateLandingJapaneseFormatter Default =
            new AppUpdateLandingJapaneseFormatter();

        public AppUpdateLandingDisplay Format(AppUpdateLandingStatus status)
        {
            if (status == null)
            {
                throw new ArgumentNullException(nameof(status));
            }

            return Format(status, DateTimeOffset.UtcNow);
        }

        public AppUpdateLandingDisplay Format(AppUpdateLandingStatus status, DateTimeOffset now)
        {
            if (status == null)
            {
                throw new ArgumentNullException(nameof(status));
            }

            var statusLabel = StatusLabel(status.State);
            var dateRange = DateRange(status.StartAt, status.EndAt);
            var timingText = TimingText(status, now);
            var parts = new List<string> { statusLabel };
            if (!string.IsNullOrEmpty(dateRange))
            {
                parts.Add(dateRange);
            }
            if (!string.IsNullOrEmpty(timingText))
            {
                parts.Add(timingText);
            }

            return new AppUpdateLandingDisplay(
                statusLabel,
                dateRange,
                timingText,
                string.Join(" ", parts));
        }

        private static string StatusLabel(AppUpdateLandingEventState state)
        {
            switch (state)
            {
                case AppUpdateLandingEventState.Disabled:
                    return "[イベントなし]";
                case AppUpdateLandingEventState.Upcoming:
                    return "[イベント待ち]";
                case AppUpdateLandingEventState.WaitingForRelease:
                    return "[アップデート待ち]";
                case AppUpdateLandingEventState.Ended:
                    return "[イベント終了]";
                default:
                    return "[イベント開催中]";
            }
        }

        private static string DateRange(DateTimeOffset? startAt, DateTimeOffset? endAt)
        {
            if (!startAt.HasValue && !endAt.HasValue)
            {
                return string.Empty;
            }

            if (startAt.HasValue && endAt.HasValue)
            {
                return FormatDate(startAt.Value) + "〜" + FormatDate(endAt.Value);
            }

            return startAt.HasValue
                ? FormatDate(startAt.Value) + "〜"
                : "〜" + FormatDate(endAt.Value);
        }

        private static string TimingText(AppUpdateLandingStatus status, DateTimeOffset now)
        {
            if (status.State == AppUpdateLandingEventState.Disabled)
            {
                return string.Empty;
            }

            if (status.Phase == AppUpdateLandingEventPhase.Upcoming && status.StartAt.HasValue)
            {
                return "あと" + RoundedUpDaysBetween(now, status.StartAt.Value) + "日後に開催";
            }

            if (status.Phase == AppUpdateLandingEventPhase.Active && status.EndAt.HasValue)
            {
                return "あと" + RoundedUpDaysBetween(now, status.EndAt.Value) + "日で終了";
            }

            return status.Phase == AppUpdateLandingEventPhase.Ended
                ? "終了しました"
                : string.Empty;
        }

        private static int RoundedUpDaysBetween(DateTimeOffset from, DateTimeOffset to)
        {
            var seconds = (to.ToUniversalTime() - from.ToUniversalTime()).TotalSeconds;
            return Math.Max(1, (int)Math.Ceiling(seconds / 86400d));
        }

        private static string FormatDate(DateTimeOffset value)
        {
            return value.ToString("MM/dd", CultureInfo.InvariantCulture);
        }
    }
}
