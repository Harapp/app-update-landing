using System;
using System.Globalization;
using System.Text.RegularExpressions;
using UnityEngine;

namespace Harapeco.AppUpdateLanding
{
    internal static class AppUpdateLandingResponseParser
    {
        private const int SupportedSchemaVersion = 2;
        private static readonly Regex VersionPattern =
            new Regex(@"\A\d{1,9}(?:\.\d{1,9}){1,3}\z", RegexOptions.CultureInvariant);

        private static readonly Regex TimestampPattern =
            new Regex(@"(?:Z|[+-]\d{2}:\d{2})\z", RegexOptions.CultureInvariant | RegexOptions.IgnoreCase);

        public static AppUpdateLandingStatus Parse(
            string json,
            AppUpdateLandingPlatform platform,
            DateTimeOffset fetchedAt)
        {
            AppUpdateLandingResponse response;
            try
            {
                response = JsonUtility.FromJson<AppUpdateLandingResponse>(json);
            }
            catch (Exception exception)
            {
                throw InvalidResponse("Response body is not valid JSON.", exception);
            }

            if (response == null)
            {
                throw InvalidResponse("Response body is empty.");
            }

            if (response.schemaVersion != SupportedSchemaVersion)
            {
                throw new AppUpdateLandingException(
                    "unsupported_schema_version",
                    "The App Update Landing API schema version is not supported.");
            }

            if (string.IsNullOrWhiteSpace(response.releaseVersion)
                || !VersionPattern.IsMatch(response.releaseVersion))
            {
                throw InvalidResponse("releaseVersion is invalid.");
            }

            if (!AppUpdateLandingUrlBuilder.TryCreateAllowedWebUri(response.pageUrl, out _))
            {
                throw InvalidResponse("pageUrl is invalid.");
            }

            if (response.eventPeriod == null)
            {
                throw InvalidResponse("eventPeriod is required.");
            }

            var startAt = ParseDate(response.eventPeriod.startAt, "eventPeriod.startAt");
            var endAt = ParseDate(response.eventPeriod.endAt, "eventPeriod.endAt");
            if (startAt.HasValue && endAt.HasValue && startAt.Value >= endAt.Value)
            {
                throw InvalidResponse("eventPeriod.startAt must be earlier than eventPeriod.endAt.");
            }

            var phase = ParsePhase(response.eventPeriod.phase);
            var platformStatus = ResolvePlatformStatus(response.platforms, platform);
            return new AppUpdateLandingStatus(
                response.schemaVersion,
                response.releaseVersion,
                response.pageUrl,
                response.enabled,
                startAt,
                endAt,
                phase,
                platform,
                platformStatus.released,
                fetchedAt);
        }

        private static AppUpdateLandingPlatformStatus ResolvePlatformStatus(
            AppUpdateLandingPlatforms platforms,
            AppUpdateLandingPlatform platform)
        {
            if (platforms == null)
            {
                throw InvalidResponse("platforms is required.");
            }

            AppUpdateLandingPlatformStatus status;
            switch (platform)
            {
                case AppUpdateLandingPlatform.Ios:
                    status = platforms.ios;
                    break;
                case AppUpdateLandingPlatform.Android:
                    status = platforms.android;
                    break;
                default:
                    status = platforms.pc;
                    break;
            }

            return status ?? throw InvalidResponse("The current platform status is required.");
        }

        private static AppUpdateLandingEventPhase ParsePhase(string value)
        {
            switch (value)
            {
                case "upcoming":
                    return AppUpdateLandingEventPhase.Upcoming;
                case "active":
                    return AppUpdateLandingEventPhase.Active;
                case "ended":
                    return AppUpdateLandingEventPhase.Ended;
                default:
                    throw InvalidResponse("eventPeriod.phase is invalid.");
            }
        }

        private static DateTimeOffset? ParseDate(string value, string fieldName)
        {
            if (string.IsNullOrEmpty(value))
            {
                return null;
            }

            if (!TimestampPattern.IsMatch(value)
                || !DateTimeOffset.TryParse(
                    value,
                    CultureInfo.InvariantCulture,
                    DateTimeStyles.RoundtripKind,
                    out var result))
            {
                throw InvalidResponse(fieldName + " is invalid.");
            }

            return result;
        }

        private static AppUpdateLandingException InvalidResponse(
            string message,
            Exception innerException = null)
        {
            return innerException == null
                ? new AppUpdateLandingException("invalid_response", message)
                : new AppUpdateLandingException("invalid_response", message, innerException);
        }
    }

    [Serializable]
    internal sealed class AppUpdateLandingResponse
    {
        public int schemaVersion;
        public string releaseVersion;
        public string pageUrl;
        public bool enabled;
        public AppUpdateLandingEventPeriod eventPeriod;
        public AppUpdateLandingPlatforms platforms;
    }

    [Serializable]
    internal sealed class AppUpdateLandingEventPeriod
    {
        public string startAt;
        public string endAt;
        public string phase;
    }

    [Serializable]
    internal sealed class AppUpdateLandingPlatforms
    {
        public AppUpdateLandingPlatformStatus ios;
        public AppUpdateLandingPlatformStatus android;
        public AppUpdateLandingPlatformStatus pc;
    }

    [Serializable]
    internal sealed class AppUpdateLandingPlatformStatus
    {
        public bool released;
    }
}
