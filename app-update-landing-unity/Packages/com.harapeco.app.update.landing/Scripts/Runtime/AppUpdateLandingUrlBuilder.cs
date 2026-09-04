using System;
using System.Collections.Generic;

namespace Harapeco.AppUpdateLanding
{
    public static class AppUpdateLandingUrlBuilder
    {
        private static readonly string[] EnvironmentParameterNames =
        {
            "appVersion",
            "locale",
            "platform",
            "osVersion",
            "targetVersion"
        };

        public static string Build(string pageUrl, AppUpdateLandingEnvironment environment)
        {
            if (environment == null)
            {
                throw new ArgumentNullException(nameof(environment));
            }

            if (!TryCreateAllowedWebUri(pageUrl, out var uri))
            {
                throw new AppUpdateLandingException(
                    "invalid_page_url",
                    "pageUrl must be an absolute HTTPS URL. HTTP is allowed only for loopback development URLs.");
            }

            var queryParts = new List<string>();
            if (!string.IsNullOrEmpty(uri.Query))
            {
                foreach (var existingPart in uri.Query.Substring(1).Split('&'))
                {
                    if (!string.IsNullOrEmpty(existingPart) && !HasEnvironmentParameterName(existingPart))
                    {
                        queryParts.Add(existingPart);
                    }
                }
            }

            AddIfPresent(queryParts, "appVersion", environment.AppVersion);
            AddIfPresent(queryParts, "locale", environment.Locale);
            queryParts.Add("platform=" + Encode(AppUpdateLandingEnvironment.PlatformName(environment.Platform)));
            AddIfPresent(queryParts, "osVersion", environment.OsVersion);

            return uri.GetLeftPart(UriPartial.Path)
                   + (queryParts.Count == 0 ? string.Empty : "?" + string.Join("&", queryParts))
                   + uri.Fragment;
        }

        internal static bool TryCreateAllowedWebUri(string value, out Uri uri)
        {
            if (!Uri.TryCreate(value, UriKind.Absolute, out uri) || string.IsNullOrEmpty(uri.Host))
            {
                uri = null;
                return false;
            }

            return uri.Scheme == Uri.UriSchemeHttps
                   || (uri.Scheme == Uri.UriSchemeHttp && uri.IsLoopback);
        }

        private static void AddIfPresent(List<string> queryParts, string name, string value)
        {
            if (!string.IsNullOrEmpty(value))
            {
                queryParts.Add(name + "=" + Encode(value));
            }
        }

        private static bool HasEnvironmentParameterName(string queryPart)
        {
            var equalsIndex = queryPart.IndexOf('=');
            var rawName = equalsIndex < 0 ? queryPart : queryPart.Substring(0, equalsIndex);
            var name = Uri.UnescapeDataString(rawName.Replace('+', ' '));
            foreach (var parameterName in EnvironmentParameterNames)
            {
                if (string.Equals(name, parameterName, StringComparison.Ordinal))
                {
                    return true;
                }
            }

            return false;
        }

        private static string Encode(string value)
        {
            return Uri.EscapeDataString(value ?? string.Empty);
        }
    }
}
