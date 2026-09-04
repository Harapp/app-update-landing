using System;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using UnityEditor;
using UnityEngine;

namespace Harapeco.AppUpdateLanding.Development.Editor
{
    internal static class AppUpdateLandingSampleExporter
    {
        private const string SourceAssetPath = "Assets/Samples/AppUpdateLanding";
        private const string DestinationAssetPath =
            "Packages/com.harapeco.app.update.landing/Samples~/AppUpdateLanding";

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

        [MenuItem("Window/App Update Landing/Export Sample")]
        public static void Export()
        {
            var sourceFullPath = GetProjectFullPath(SourceAssetPath);
            if (!Directory.Exists(sourceFullPath))
            {
                Debug.LogError(
                    $"[App Update Landing] Sample source does not exist: {SourceAssetPath}");
                return;
            }

            var files = CollectExportFiles(sourceFullPath).ToList();
            if (files.Count == 0)
            {
                Debug.LogWarning(
                    $"[App Update Landing] Sample source has no exportable files: {SourceAssetPath}");
                return;
            }

            CopyFiles(files);
            AssetDatabase.Refresh();
            Debug.Log(
                $"[App Update Landing] Sample exported to {DestinationAssetPath}");
        }

        private static IEnumerable<ExportFile> CollectExportFiles(string sourceFullPath)
        {
            foreach (var sourceFilePath in Directory.GetFiles(
                         sourceFullPath,
                         "*",
                         SearchOption.AllDirectories))
            {
                var sourceAssetPath = ToAssetPath(sourceFilePath);
                var relativePath = NormalizeAssetPath(
                    sourceAssetPath.Substring(SourceAssetPath.Length).TrimStart('/'));
                if (!ShouldIncludeFile(relativePath))
                {
                    continue;
                }

                yield return new ExportFile(
                    sourceFilePath,
                    $"{DestinationAssetPath}/{relativePath}");
            }
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

            return AllowedAssetExtensions.Any(allowed => string.Equals(
                assetExtension,
                allowed,
                StringComparison.OrdinalIgnoreCase));
        }

        private static string GetAssetFileName(string relativePath)
        {
            var fileName = Path.GetFileName(relativePath);
            if (!fileName.EndsWith(".meta", StringComparison.OrdinalIgnoreCase))
            {
                return fileName;
            }

            var assetPath = relativePath.Substring(
                0,
                relativePath.Length - ".meta".Length);
            return Path.GetFileName(assetPath);
        }

        private static void CopyFiles(IEnumerable<ExportFile> files)
        {
            foreach (var file in files)
            {
                var destinationFullPath = GetProjectFullPath(file.DestinationAssetPath);
                var destinationDirectory = Path.GetDirectoryName(destinationFullPath);
                if (!string.IsNullOrEmpty(destinationDirectory))
                {
                    Directory.CreateDirectory(destinationDirectory);
                }

                File.Copy(file.SourceFullPath, destinationFullPath, true);
            }
        }

        private static string GetProjectFullPath(string assetPath)
        {
            return Path.GetFullPath(Path.Combine(Application.dataPath, "..", assetPath));
        }

        private static string ToAssetPath(string fullPath)
        {
            var projectRoot = Path.GetFullPath(Path.Combine(Application.dataPath, ".."));
            var relativePath = fullPath.Substring(projectRoot.Length)
                .TrimStart(Path.DirectorySeparatorChar, Path.AltDirectorySeparatorChar);
            return NormalizeAssetPath(relativePath);
        }

        private static string NormalizeAssetPath(string path)
        {
            return path.Replace('\\', '/');
        }

        private readonly struct ExportFile
        {
            public readonly string SourceFullPath;
            public readonly string DestinationAssetPath;

            public ExportFile(string sourceFullPath, string destinationAssetPath)
            {
                SourceFullPath = sourceFullPath;
                DestinationAssetPath = destinationAssetPath;
            }
        }
    }
}
