using System;
using System.Globalization;
using System.Text.RegularExpressions;
using UnityEngine;

namespace Harapeco.AppUpdateLanding
{
    public sealed class AppUpdateLandingEnvironment
    {
        private static readonly Regex AppVersionPattern =
            new Regex(@"\A\d{1,9}(?:\.\d{1,9}){1,3}\z", RegexOptions.CultureInvariant);

        private static readonly Regex OsVersionPattern =
            new Regex(@"\A\d{1,9}(?:\.\d{1,9}){0,3}\z", RegexOptions.CultureInvariant);

        private static readonly Regex VersionInOperatingSystemPattern =
            new Regex(@"(?<!\d)\d+(?:\.\d+)*(?!\d)", RegexOptions.CultureInvariant);

        public AppUpdateLandingEnvironment(
            string appVersion,
            string locale,
            AppUpdateLandingPlatform platform,
            string osVersion)
        {
            AppVersion = NormalizeVersion(appVersion, false);
            Locale = NormalizeLocale(locale);
            Platform = platform;
            OsVersion = NormalizeVersion(osVersion, true);
        }

        public string AppVersion { get; }

        public string Locale { get; }

        public AppUpdateLandingPlatform Platform { get; }

        public string OsVersion { get; }

        public static AppUpdateLandingEnvironment FromUnity()
        {
            return new AppUpdateLandingEnvironment(
                Application.version,
                ResolveLocale(),
                ResolvePlatform(),
                ResolveOsVersion());
        }

        internal static string PlatformName(AppUpdateLandingPlatform platform)
        {
            switch (platform)
            {
                case AppUpdateLandingPlatform.Ios:
                    return "ios";
                case AppUpdateLandingPlatform.Android:
                    return "android";
                default:
                    return "pc";
            }
        }

        private static AppUpdateLandingPlatform ResolvePlatform()
        {
#if UNITY_IOS && !UNITY_EDITOR
            return AppUpdateLandingPlatform.Ios;
#elif UNITY_ANDROID && !UNITY_EDITOR
            return AppUpdateLandingPlatform.Android;
#else
            return AppUpdateLandingPlatform.Pc;
#endif
        }

        private static string ResolveLocale()
        {
            var locale = CultureInfo.CurrentUICulture?.Name;
            if (!string.IsNullOrWhiteSpace(locale))
            {
                return locale;
            }

            switch (Application.systemLanguage)
            {
                case SystemLanguage.Japanese:
                    return "ja";
                case SystemLanguage.English:
                    return "en";
                default:
                    return string.Empty;
            }
        }

        private static string ResolveOsVersion()
        {
#if UNITY_IOS && !UNITY_EDITOR
            return UnityEngine.iOS.Device.systemVersion;
#elif UNITY_ANDROID && !UNITY_EDITOR
            try
            {
                using (var version = new AndroidJavaClass("android.os.Build$VERSION"))
                {
                    return version.GetStatic<string>("RELEASE");
                }
            }
            catch
            {
                return ExtractVersion(SystemInfo.operatingSystem);
            }
#else
            return ExtractVersion(SystemInfo.operatingSystem);
#endif
        }

        private static string ExtractVersion(string value)
        {
            if (string.IsNullOrWhiteSpace(value))
            {
                return string.Empty;
            }

            var match = VersionInOperatingSystemPattern.Match(value);
            return match.Success ? NormalizeVersion(match.Value, true) : string.Empty;
        }

        private static string NormalizeVersion(string value, bool allowSingleSegment)
        {
            var normalized = string.IsNullOrWhiteSpace(value) ? string.Empty : value.Trim();
            var pattern = allowSingleSegment ? OsVersionPattern : AppVersionPattern;
            return pattern.IsMatch(normalized) ? normalized : string.Empty;
        }

        private static string NormalizeLocale(string value)
        {
            if (string.IsNullOrWhiteSpace(value))
            {
                return string.Empty;
            }

            var normalized = value.Trim().Replace('_', '-');
            if (normalized.Length > 35)
            {
                return string.Empty;
            }

            var parts = normalized.Split('-');
            for (var index = 0; index < parts.Length; index++)
            {
                var part = parts[index];
                var minimumLength = index == 0 ? 2 : 1;
                if (part.Length < minimumLength || part.Length > 8)
                {
                    return string.Empty;
                }

                foreach (var character in part)
                {
                    if (index == 0 ? !IsAsciiLetter(character) : !IsAsciiLetterOrDigit(character))
                    {
                        return string.Empty;
                    }
                }
            }

            return normalized;
        }

        private static bool IsAsciiLetter(char value)
        {
            return value >= 'A' && value <= 'Z' || value >= 'a' && value <= 'z';
        }

        private static bool IsAsciiLetterOrDigit(char value)
        {
            return IsAsciiLetter(value) || value >= '0' && value <= '9';
        }
    }
}
