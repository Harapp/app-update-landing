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

        [SerializeField]
        [Min(0)]
        [Tooltip("イベントがない場合の1回目の再取得間隔（日）。未設定時は1日です。")]
        private float firstBackoffDays = 1f;

        [SerializeField]
        [Min(0)]
        [Tooltip("イベントがない場合の2回目の再取得間隔（日）。未設定時は2日です。")]
        private float secondBackoffDays = 2f;

        [SerializeField]
        [Min(0)]
        [Tooltip("イベントがない場合の3回目の再取得間隔（日）。未設定時は3日です。")]
        private float thirdBackoffDays = 3f;

        [SerializeField]
        [Min(0)]
        [Tooltip("再取得間隔の最大値（日）。未設定時は3日です。")]
        private float maximumBackoffDays = 3f;

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

        public float FirstBackoffDays => NormalizeBackoffDays(firstBackoffDays, 1f);

        public float SecondBackoffDays => NormalizeBackoffDays(secondBackoffDays, 2f);

        public float ThirdBackoffDays => NormalizeBackoffDays(thirdBackoffDays, 3f);

        public float MaximumBackoffDays => NormalizeBackoffDays(maximumBackoffDays, 3f);

        internal float GetBackoffDays(int attempt)
        {
            var configuredDays = attempt <= 0
                ? FirstBackoffDays
                : attempt == 1
                    ? SecondBackoffDays
                    : attempt == 2 ? ThirdBackoffDays : MaximumBackoffDays;
            return Math.Min(configuredDays, MaximumBackoffDays);
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

        internal void ConfigureBackoffForTesting(
            float firstDays,
            float secondDays,
            float thirdDays,
            float maximumDays)
        {
            firstBackoffDays = firstDays;
            secondBackoffDays = secondDays;
            thirdBackoffDays = thirdDays;
            maximumBackoffDays = maximumDays;
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

        private static float NormalizeBackoffDays(float value, float fallback)
        {
            return value > 0f && !float.IsNaN(value) && !float.IsInfinity(value)
                ? value
                : fallback;
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
