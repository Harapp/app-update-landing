using System;
using System.IO;
using System.Text.RegularExpressions;
using UnityEditor;
using UnityEditor.TestTools.TestRunner.Api;
using UnityEngine;

namespace Harapeco.AppUpdateLanding.Development.Editor
{
    internal sealed class AppUpdateLandingUnityReleaseWindow : EditorWindow
    {
        private const string PackageRelativePath =
            "Packages/com.harapeco.app.update.landing/package.json";
        private const string VersionRelativePath =
            "Packages/com.harapeco.app.update.landing/Scripts/Runtime/AppUpdateLandingVersion.cs";

        private static readonly Regex VersionRegex =
            new Regex(@"^[0-9]{1,9}\.[0-9]{1,9}\.[0-9]{1,9}$", RegexOptions.Compiled);
        private static readonly Regex PackageVersionRegex =
            new Regex("\"version\"\\s*:\\s*\"(?<version>[^\"]+)\"", RegexOptions.Compiled);
        private static readonly Regex RuntimeVersionRegex = new Regex(
            @"public\s+const\s+string\s+Value\s*=\s*""(?<version>[^""]+)""\s*;",
            RegexOptions.Compiled);

        private string packageVersion = "(unknown)";
        private string runtimeVersion = "(unknown)";
        private string nextVersion = string.Empty;
        private string statusMessage = string.Empty;
        private MessageType statusType = MessageType.None;
        private bool isRunningTests;
        private bool releaseAfterTests;
        private string pendingReleaseVersion = string.Empty;
        private TestRunnerApi testRunnerApi;
        private ReleaseTestCallbacks callbacks;

        [MenuItem("Window/App Update Landing/Release")]
        public static void Open()
        {
            var window = GetWindow<AppUpdateLandingUnityReleaseWindow>(
                "App Update Landing Release");
            window.minSize = new Vector2(470, 340);
            window.Show();
        }

        private void OnEnable()
        {
            RefreshCurrentVersions();
        }

        private void OnDisable()
        {
            UnregisterCallbacks();
        }

        private void OnGUI()
        {
            using (new EditorGUI.DisabledScope(isRunningTests))
            {
                var style = new GUIStyle { padding = new RectOffset(10, 10, 8, 10) };
                using (new GUILayout.VerticalScope(style))
                {
                    EditorGUILayout.LabelField(
                        "App Update Landing Unity Release",
                        EditorStyles.boldLabel);
                    EditorGUILayout.HelpBox(
                        "Release updates the package version files after all EditMode tests pass. "
                        + "Commit, push, and package tagging are not executed by this window.",
                        MessageType.Info);
                    EditorGUILayout.Space(6);

                    DrawVersion("Current package.json version", packageVersion);
                    DrawVersion("Current runtime version", runtimeVersion);

                    EditorGUILayout.Space(4);
                    nextVersion = EditorGUILayout.TextField("Next version", nextVersion);

                    if (!VersionsMatch())
                    {
                        EditorGUILayout.HelpBox(
                            "package.json version and AppUpdateLandingVersion.Value do not match. "
                            + "Release is blocked until they are aligned.",
                            MessageType.Error);
                    }

                    if (!string.IsNullOrEmpty(statusMessage))
                    {
                        EditorGUILayout.HelpBox(statusMessage, statusType);
                    }

                    EditorGUILayout.Space(8);
                    using (new EditorGUILayout.HorizontalScope())
                    {
                        if (GUILayout.Button("Refresh"))
                        {
                            RefreshCurrentVersions();
                        }

                        if (GUILayout.Button("Dry Run"))
                        {
                            DryRun();
                        }
                    }

                    if (GUILayout.Button("Run EditMode Tests"))
                    {
                        RunEditModeTests(false);
                    }

                    if (GUILayout.Button("Release"))
                    {
                        RunRelease();
                    }
                }
            }

            if (isRunningTests)
            {
                EditorGUILayout.HelpBox(
                    "EditMode tests are running. Version files are updated only if the run passes.",
                    MessageType.Info);
            }
        }

        private static void DrawVersion(string label, string value)
        {
            using (new EditorGUILayout.HorizontalScope())
            {
                EditorGUILayout.LabelField(label, GUILayout.Width(230));
                EditorGUILayout.SelectableLabel(
                    value,
                    GUILayout.Height(EditorGUIUtility.singleLineHeight));
            }
        }

        private void RefreshCurrentVersions()
        {
            var read = ReadCurrentVersions();
            packageVersion = read.PackageVersion ?? "(error)";
            runtimeVersion = read.RuntimeVersion ?? "(error)";

            if (!read.IsValid)
            {
                SetStatus(read.Error, MessageType.Error);
                return;
            }

            if (string.IsNullOrWhiteSpace(nextVersion) || nextVersion == packageVersion)
            {
                nextVersion = IncrementPatch(packageVersion);
            }

            SetStatus(string.Empty, MessageType.None);
        }

        private void DryRun()
        {
            if (!ValidateReleaseCandidate(nextVersion, out var read, out var error))
            {
                SetStatus(error, MessageType.Error);
                return;
            }

            SetStatus(
                $"Dry run OK. Would update {PackageRelativePath} and {VersionRelativePath} "
                + $"from {read.PackageVersion} to {nextVersion}. No files were changed.",
                MessageType.Info);
        }

        private void RunRelease()
        {
            if (!ValidateReleaseCandidate(nextVersion, out _, out var error))
            {
                SetStatus(error, MessageType.Error);
                return;
            }

            RunEditModeTests(true);
        }

        private void RunEditModeTests(bool shouldReleaseAfterTests)
        {
            if (!ValidateCurrentVersions(out var error))
            {
                SetStatus(error, MessageType.Error);
                return;
            }

            releaseAfterTests = shouldReleaseAfterTests;
            pendingReleaseVersion = nextVersion;
            isRunningTests = true;
            SetStatus("Running all EditMode tests...", MessageType.Info);

            testRunnerApi = ScriptableObject.CreateInstance<TestRunnerApi>();
            callbacks = new ReleaseTestCallbacks(OnTestRunFinished);
            testRunnerApi.RegisterCallbacks(callbacks);
            testRunnerApi.Execute(new ExecutionSettings(new Filter { testMode = TestMode.EditMode }));
        }

        private void OnTestRunFinished(ITestResultAdaptor result)
        {
            UnregisterCallbacks();
            isRunningTests = false;

            if (result.Test == null || result.Test.TestCaseCount == 0)
            {
                SetStatus(
                    "EditMode test run did not find any tests. Version files were not changed.",
                    MessageType.Error);
                Repaint();
                return;
            }

            if (result.FailCount > 0
                || result.InconclusiveCount > 0
                || !string.Equals(result.ResultState, "Passed", StringComparison.Ordinal))
            {
                SetStatus(
                    $"EditMode tests did not pass. Result: {result.ResultState}, "
                    + $"passed: {result.PassCount}, failed: {result.FailCount}, "
                    + $"inconclusive: {result.InconclusiveCount}. Version files were not changed.",
                    MessageType.Error);
                Repaint();
                return;
            }

            if (!releaseAfterTests)
            {
                SetStatus($"EditMode tests passed. Passed: {result.PassCount}.", MessageType.Info);
                Repaint();
                return;
            }

            if (!ValidateReleaseCandidate(pendingReleaseVersion, out _, out var error))
            {
                SetStatus(error, MessageType.Error);
                Repaint();
                return;
            }

            try
            {
                UpdateVersionFiles(pendingReleaseVersion);
                AssetDatabase.Refresh();
                RefreshCurrentVersions();
                SetStatus(
                    $"Release version updated to {pendingReleaseVersion}. "
                    + "Review the changes, then commit, push, and create the package release tag.",
                    MessageType.Info);
            }
            catch (Exception exception)
            {
                SetStatus($"Version update failed: {exception.Message}", MessageType.Error);
            }

            Repaint();
        }

        private void UnregisterCallbacks()
        {
            if (testRunnerApi != null && callbacks != null)
            {
                testRunnerApi.UnregisterCallbacks(callbacks);
            }

            if (testRunnerApi != null)
            {
                DestroyImmediate(testRunnerApi);
                testRunnerApi = null;
            }

            callbacks = null;
        }

        private bool ValidateCurrentVersions(out string error)
        {
            var read = ReadCurrentVersions();
            if (!read.IsValid)
            {
                error = read.Error;
                return false;
            }

            if (read.PackageVersion != read.RuntimeVersion)
            {
                error =
                    $"Current versions do not match. package.json={read.PackageVersion}, "
                    + $"AppUpdateLandingVersion={read.RuntimeVersion}.";
                return false;
            }

            error = string.Empty;
            return true;
        }

        private bool ValidateReleaseCandidate(
            string candidateVersion,
            out VersionReadResult read,
            out string error)
        {
            read = ReadCurrentVersions();
            if (!read.IsValid)
            {
                error = read.Error;
                return false;
            }

            if (read.PackageVersion != read.RuntimeVersion)
            {
                error =
                    $"Current versions do not match. package.json={read.PackageVersion}, "
                    + $"AppUpdateLandingVersion={read.RuntimeVersion}.";
                return false;
            }

            if (!TryParseVersion(read.PackageVersion, out var current))
            {
                error = $"Current version is invalid: {read.PackageVersion} (expected X.Y.Z).";
                return false;
            }

            if (!TryParseVersion(candidateVersion, out var next))
            {
                error =
                    $"Invalid next version: {candidateVersion} "
                    + "(expected X.Y.Z; pre-release versions are not supported).";
                return false;
            }

            if (CompareVersion(next, current) <= 0)
            {
                error =
                    $"Next version must be greater than current version. "
                    + $"current={read.PackageVersion}, next={candidateVersion}.";
                return false;
            }

            error = string.Empty;
            return true;
        }

        private static VersionReadResult ReadCurrentVersions()
        {
            try
            {
                var packageJson = File.ReadAllText(ProjectPath(PackageRelativePath));
                var packageMatches = PackageVersionRegex.Matches(packageJson);
                if (packageMatches.Count != 1)
                {
                    return VersionReadResult.Failed(
                        $"Expected exactly one version field in {PackageRelativePath}, "
                        + $"found {packageMatches.Count}.");
                }

                var versionSource = File.ReadAllText(ProjectPath(VersionRelativePath));
                var versionMatches = RuntimeVersionRegex.Matches(versionSource);
                if (versionMatches.Count != 1)
                {
                    return VersionReadResult.Failed(
                        $"Expected exactly one AppUpdateLandingVersion.Value constant in "
                        + $"{VersionRelativePath}, found {versionMatches.Count}.");
                }

                return VersionReadResult.Succeeded(
                    packageMatches[0].Groups["version"].Value,
                    versionMatches[0].Groups["version"].Value);
            }
            catch (Exception exception)
            {
                return VersionReadResult.Failed(exception.Message);
            }
        }

        private static void UpdateVersionFiles(string version)
        {
            var packagePath = ProjectPath(PackageRelativePath);
            var packageJson = File.ReadAllText(packagePath);
            var packageMatches = PackageVersionRegex.Matches(packageJson);
            if (packageMatches.Count != 1)
            {
                throw new InvalidOperationException(
                    $"Expected exactly one version field in {PackageRelativePath}, "
                    + $"found {packageMatches.Count}.");
            }

            packageJson = PackageVersionRegex.Replace(packageJson, $"\"version\": \"{version}\"", 1);
            File.WriteAllText(packagePath, packageJson);

            var versionPath = ProjectPath(VersionRelativePath);
            var versionSource = File.ReadAllText(versionPath);
            var versionMatches = RuntimeVersionRegex.Matches(versionSource);
            if (versionMatches.Count != 1)
            {
                throw new InvalidOperationException(
                    $"Expected exactly one AppUpdateLandingVersion.Value constant in "
                    + $"{VersionRelativePath}, found {versionMatches.Count}.");
            }

            versionSource = RuntimeVersionRegex.Replace(
                versionSource,
                $"public const string Value = \"{version}\";",
                1);
            File.WriteAllText(versionPath, versionSource);
        }

        private static string ProjectPath(string relativePath)
        {
            return Path.GetFullPath(
                Path.Combine(Directory.GetParent(Application.dataPath).FullName, relativePath));
        }

        private static bool TryParseVersion(string value, out int[] parts)
        {
            parts = null;
            if (string.IsNullOrWhiteSpace(value) || !VersionRegex.IsMatch(value))
            {
                return false;
            }

            var split = value.Split('.');
            if (!int.TryParse(split[0], out var major)
                || !int.TryParse(split[1], out var minor)
                || !int.TryParse(split[2], out var patch))
            {
                return false;
            }

            parts = new[] { major, minor, patch };
            return true;
        }

        private static int CompareVersion(int[] left, int[] right)
        {
            for (var index = 0; index < 3; index++)
            {
                var comparison = left[index].CompareTo(right[index]);
                if (comparison != 0)
                {
                    return comparison;
                }
            }

            return 0;
        }

        private static string IncrementPatch(string version)
        {
            if (!TryParseVersion(version, out var parts))
            {
                return version;
            }

            return $"{parts[0]}.{parts[1]}.{parts[2] + 1}";
        }

        private bool VersionsMatch()
        {
            return string.Equals(packageVersion, runtimeVersion, StringComparison.Ordinal);
        }

        private void SetStatus(string message, MessageType type)
        {
            statusMessage = message;
            statusType = type;
        }

        private readonly struct VersionReadResult
        {
            private VersionReadResult(string packageVersion, string runtimeVersion, string error)
            {
                PackageVersion = packageVersion;
                RuntimeVersion = runtimeVersion;
                Error = error;
            }

            public string PackageVersion { get; }

            public string RuntimeVersion { get; }

            public string Error { get; }

            public bool IsValid => string.IsNullOrEmpty(Error);

            public static VersionReadResult Succeeded(
                string packageVersion,
                string runtimeVersion)
            {
                return new VersionReadResult(packageVersion, runtimeVersion, string.Empty);
            }

            public static VersionReadResult Failed(string error)
            {
                return new VersionReadResult(null, null, error);
            }
        }

        private sealed class ReleaseTestCallbacks : ICallbacks
        {
            private readonly Action<ITestResultAdaptor> runFinished;

            public ReleaseTestCallbacks(Action<ITestResultAdaptor> runFinished)
            {
                this.runFinished = runFinished;
            }

            public void RunStarted(ITestAdaptor testsToRun)
            {
            }

            public void RunFinished(ITestResultAdaptor result)
            {
                runFinished(result);
            }

            public void TestStarted(ITestAdaptor test)
            {
            }

            public void TestFinished(ITestResultAdaptor result)
            {
            }
        }
    }
}
