using System;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using NUnit.Framework;
using UnityEngine;

namespace Harapeco.AppUpdateLanding.Development.Editor.Tests
{
    public sealed class AppUpdateLandingSampleExportTests
    {
        private static readonly string[] AllowedAssetExtensions =
        {
            ".cs",
            ".unity",
            ".md"
        };

        private static readonly string[] ForbiddenFileNames =
        {
            ".env",
            "AppUpdateLandingSettings.asset"
        };

        private static readonly string[] ForbiddenAssetExtensions =
        {
            ".key",
            ".keystore",
            ".mobileprovision",
            ".p12",
            ".pem"
        };

        [Test]
        public void PackageSampleMatchesExportableSourceFiles()
        {
            var projectRoot = Path.GetFullPath(Path.Combine(Application.dataPath, ".."));
            var sourceRoot = Path.Combine(
                projectRoot,
                "Assets/Samples/AppUpdateLanding");
            var packageRoot = Path.Combine(
                projectRoot,
                "Packages/com.harapeco.app.update.landing/Samples~/AppUpdateLanding");

            Assert.That(
                Directory.Exists(sourceRoot),
                Is.True,
                "Sample source directory does not exist.");
            Assert.That(
                Directory.Exists(packageRoot),
                Is.True,
                "Package sample directory does not exist. Run Export Sample first.");

            var expectedRelativePaths = CollectExportableRelativePaths(sourceRoot);
            var actualRelativePaths = CollectAllRelativePaths(packageRoot);

            Assert.That(
                actualRelativePaths,
                Is.EqualTo(expectedRelativePaths),
                "Package sample file list is out of sync. Run Export Sample and remove stale files.");

            foreach (var relativePath in expectedRelativePaths)
            {
                var sourceBytes = File.ReadAllBytes(Path.Combine(sourceRoot, relativePath));
                var packageBytes = File.ReadAllBytes(Path.Combine(packageRoot, relativePath));
                Assert.That(
                    packageBytes,
                    Is.EqualTo(sourceBytes),
                    $"Package sample content is out of sync: {relativePath}");
            }
        }

        private static IReadOnlyList<string> CollectExportableRelativePaths(string rootPath)
        {
            return Directory.GetFiles(rootPath, "*", SearchOption.AllDirectories)
                .Select(path => ToRelativePath(rootPath, path))
                .Where(ShouldIncludeFile)
                .OrderBy(path => path, StringComparer.Ordinal)
                .ToArray();
        }

        private static IReadOnlyList<string> CollectAllRelativePaths(string rootPath)
        {
            return Directory.GetFiles(rootPath, "*", SearchOption.AllDirectories)
                .Select(path => ToRelativePath(rootPath, path))
                .OrderBy(path => path, StringComparer.Ordinal)
                .ToArray();
        }

        private static bool ShouldIncludeFile(string relativePath)
        {
            var assetFileName = GetAssetFileName(relativePath);
            if (ForbiddenFileNames.Any(name => string.Equals(
                    assetFileName,
                    name,
                    StringComparison.OrdinalIgnoreCase)))
            {
                return false;
            }

            var assetExtension = Path.GetExtension(assetFileName);
            if (ForbiddenAssetExtensions.Any(extension => string.Equals(
                    assetExtension,
                    extension,
                    StringComparison.OrdinalIgnoreCase)))
            {
                return false;
            }

            return AllowedAssetExtensions.Any(extension => string.Equals(
                assetExtension,
                extension,
                StringComparison.OrdinalIgnoreCase));
        }

        private static string GetAssetFileName(string relativePath)
        {
            var fileName = Path.GetFileName(relativePath);
            if (!fileName.EndsWith(".meta", StringComparison.OrdinalIgnoreCase))
            {
                return fileName;
            }

            return Path.GetFileName(relativePath.Substring(
                0,
                relativePath.Length - ".meta".Length));
        }

        private static string ToRelativePath(string rootPath, string fullPath)
        {
            return fullPath.Substring(rootPath.Length)
                .TrimStart(Path.DirectorySeparatorChar, Path.AltDirectorySeparatorChar)
                .Replace('\\', '/');
        }
    }
}
