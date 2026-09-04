using UnityEditor;
using UnityEngine;

namespace Harapeco.AppUpdateLanding.Editor
{
    internal sealed class AppUpdateLandingSettingsWindow : EditorWindow
    {
        private UnityEditor.Editor settingsEditor;
        private AppUpdateLandingSettings settings;
        private Vector2 scrollPosition;

        [MenuItem("Window/App Update Landing/Settings")]
        public static void Open()
        {
            var window = GetWindow<AppUpdateLandingSettingsWindow>("App Update Landing Settings");
            window.minSize = new Vector2(420, 220);
            window.Show();
        }

        private void OnEnable()
        {
            LoadSettingsEditor();
        }

        private void OnFocus()
        {
            if (settingsEditor == null)
            {
                LoadSettingsEditor();
            }

            Repaint();
        }

        private void OnDisable()
        {
            DestroySettingsEditor();
        }

        private void OnGUI()
        {
            if (settingsEditor == null)
            {
                LoadSettingsEditor();
            }

            using (new EditorGUI.DisabledScope(Application.isPlaying))
            {
                scrollPosition = EditorGUILayout.BeginScrollView(scrollPosition);
                var style = new GUIStyle { padding = new RectOffset(10, 10, 8, 10) };
                using (new GUILayout.VerticalScope(style))
                {
                    EditorGUILayout.LabelField("App Update Landing Settings", EditorStyles.boldLabel);
                    EditorGUILayout.HelpBox(
                        "Configure the public API URL and cache refresh backoff used by "
                        + "AppUpdateLandingClient.",
                        MessageType.Info);

                    settingsEditor?.OnInspectorGUI();

                    var validation = settings == null ? null : settings.Validate();
                    if (validation != null && !validation.IsValid)
                    {
                        EditorGUILayout.HelpBox(validation.Message, MessageType.Warning);
                    }

                    if (settings != null
                        && settings.TestState != AppUpdateLandingTestState.ApiResponse)
                    {
                        EditorGUILayout.HelpBox(
                            "Test State overrides the API event state in Unity Editor only. "
                            + "The API request still runs, and Player Builds always use the API response.",
                            MessageType.Warning);
                    }
                }

                EditorGUILayout.EndScrollView();
            }
        }

        private void LoadSettingsEditor()
        {
            settings = AppUpdateLandingSettings.Current;
            DestroySettingsEditor();
            settingsEditor = UnityEditor.Editor.CreateEditor(settings);
        }

        private void DestroySettingsEditor()
        {
            if (settingsEditor == null)
            {
                return;
            }

            Object.DestroyImmediate(settingsEditor);
            settingsEditor = null;
        }
    }
}
