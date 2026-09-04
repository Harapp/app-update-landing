using System;
using UnityEngine;

#if UNITY_EDITOR
using System.IO;
using UnityEditor;
#endif

namespace Harapeco.AppUpdateLanding
{
    [CreateAssetMenu(fileName = ResourceAssetName, menuName = "App Update Landing/Settings")]
    public sealed class AppUpdateLandingSettings : ScriptableObject
    {
        public const string SettingFullPath = "Assets/Resources/AppUpdateLandingSettings.asset";
        public const string ResourceAssetName = "AppUpdateLandingSettings";
        public const string ResourcePath = ResourceAssetName;

        [SerializeField]
        [Tooltip("App Update Landingの公開API URL。通常はHTTPSの/api/を指定します。")]
        private string apiUrl = string.Empty;

        [Header("Editor Testing")]
        [SerializeField]
        [Tooltip("Unity EditorでAPI取得後の表示状態を差し替えます。Player Buildでは常にAPI Responseを使用します。")]
        private AppUpdateLandingTestState testState = AppUpdateLandingTestState.ApiResponse;

        private static AppUpdateLandingSettings instance;

        public string ApiUrl => NormalizeText(apiUrl);

        public AppUpdateLandingTestState TestState
        {
            get
            {
#if UNITY_EDITOR
                return testState;
#else
                return AppUpdateLandingTestState.ApiResponse;
#endif
            }
        }

        public static AppUpdateLandingSettings Current
        {
            get
            {
                if (instance == null)
                {
                    instance = LoadOrCreate();
                }

                return instance;
            }
        }

        public static bool HasSettings => LoadFromResources() != null;

        public static AppUpdateLandingSettings LoadFromResources()
        {
            return Resources.Load<AppUpdateLandingSettings>(ResourcePath);
        }

        public static AppUpdateLandingSettings LoadOrCreate()
        {
            return LoadFromResources() ?? Create();
        }

        public static AppUpdateLandingSettingsValidationResult ValidateLoadedSettings()
        {
            return Validate(LoadFromResources());
        }

        public static AppUpdateLandingSettingsValidationResult Validate(
            AppUpdateLandingSettings settings)
        {
            if (settings == null)
            {
                return AppUpdateLandingSettingsValidationResult.Failure(
                    AppUpdateLandingSettingsValidationCode.SettingsMissing,
                    "AppUpdateLandingSettings asset was not found in a Resources folder.");
            }

            return settings.Validate();
        }

        public AppUpdateLandingSettingsValidationResult Validate()
        {
            if (string.IsNullOrEmpty(ApiUrl))
            {
                return AppUpdateLandingSettingsValidationResult.Failure(
                    AppUpdateLandingSettingsValidationCode.ApiUrlMissing,
                    "API URL is required.");
            }

            if (!AppUpdateLandingUrlBuilder.TryCreateAllowedWebUri(ApiUrl, out _))
            {
                return AppUpdateLandingSettingsValidationResult.Failure(
                    AppUpdateLandingSettingsValidationCode.ApiUrlInvalid,
                    "API URL must be an absolute HTTPS URL. HTTP is allowed only for loopback development URLs.");
            }

            return AppUpdateLandingSettingsValidationResult.Success();
        }

        internal void ConfigureForTesting(
            string value,
            AppUpdateLandingTestState state = AppUpdateLandingTestState.ApiResponse)
        {
            apiUrl = value;
            testState = state;
        }

        private static AppUpdateLandingSettings Create()
        {
#if UNITY_EDITOR
            var settings = CreateInstance<AppUpdateLandingSettings>();
            var directory = Path.GetDirectoryName(SettingFullPath);
            if (!string.IsNullOrEmpty(directory) && !Directory.Exists(directory))
            {
                Directory.CreateDirectory(directory);
            }

            AssetDatabase.CreateAsset(settings, SettingFullPath);
            AssetDatabase.SaveAssets();
            AssetDatabase.Refresh();
            return LoadFromResources();
#else
            throw new InvalidOperationException(
                "[App Update Landing] Cannot create AppUpdateLandingSettings asset outside editor.");
#endif
        }

        private static string NormalizeText(string value)
        {
            return value == null ? string.Empty : value.Trim();
        }
    }

    public enum AppUpdateLandingTestState
    {
        [InspectorName("API Response")]
        ApiResponse = 0,

        [InspectorName("イベント無し")]
        NoEvent,

        [InspectorName("イベント前")]
        Upcoming,

        [InspectorName("アップデート待ち")]
        WaitingForRelease,

        [InspectorName("イベント中")]
        Active,

        [InspectorName("イベント後")]
        Ended
    }

    public enum AppUpdateLandingSettingsValidationCode
    {
        None = 0,
        SettingsMissing,
        ApiUrlMissing,
        ApiUrlInvalid
    }

    public sealed class AppUpdateLandingSettingsValidationResult
    {
        private AppUpdateLandingSettingsValidationResult(
            bool isValid,
            AppUpdateLandingSettingsValidationCode code,
            string message)
        {
            IsValid = isValid;
            Code = code;
            Message = message;
        }

        public bool IsValid { get; }

        public AppUpdateLandingSettingsValidationCode Code { get; }

        public string Message { get; }

        public static AppUpdateLandingSettingsValidationResult Success()
        {
            return new AppUpdateLandingSettingsValidationResult(
                true,
                AppUpdateLandingSettingsValidationCode.None,
                string.Empty);
        }

        public static AppUpdateLandingSettingsValidationResult Failure(
            AppUpdateLandingSettingsValidationCode code,
            string message)
        {
            return new AppUpdateLandingSettingsValidationResult(false, code, message);
        }
    }
}
