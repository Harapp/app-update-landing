# App Update Landing

UnityクライアントとUpdateランディングページ用サーバーを同じリポジトリで管理します。

```text
app-update-landing/
├── app-update-landing-unity/   # Unityプロジェクト
├── app-update-landing-server/  # PHPサーバー
└── scripts/                    # preview・deploy・releaseコマンド
```

サーバーの仕様、開発方法、ローカルプレビューについては
[サーバーREADME](app-update-landing-server/README.md)を参照してください。

## Unityパッケージ

Unityクライアントは、UPMパッケージ
[`com.harapeco.app.update.landing`](app-update-landing-unity/Packages/com.harapeco.app.update.landing/README.md)として管理します。
公開APIからイベント状態を取得し、Hostアプリの表示・詳細ダイアログ・ランディングページ遷移へ接続できます。

Unity Package Managerの`Install package from Git URL`で導入できます。

```text
https://github.com/Harapp/app-update-landing.git?path=/app-update-landing-unity/Packages/com.harapeco.app.update.landing
```

Unityパッケージのバージョン更新は、Unity Editorの
`Window > App Update Landing > Release`から確認できます。Releaseは全EditMode Test成功後に
`package.json`と`AppUpdateLandingVersion.Value`を同期更新しますが、commit・push・tag作成は行いません。

更新後は、リポジトリルートで次を実行します。

```sh
scripts/release-unity-tag [<version>]
```

このコマンドはversionファイルを検証してrelease commitと`unity-vX.Y.Z`タグを作成し、
`main`とタグをoriginへatomic pushします。変更内容だけを確認する場合は`--dry-run`を指定します。

インストール後は、Package Managerの`Samples`欄から`App Update Landing Sample`をImportできます。
開発用Unityプロジェクトでは`Assets/Samples/AppUpdateLanding`をSampleの正本とし、
`Window > App Update Landing > Export Sample`からPackageの`Samples~`へ同期します。
Settingsの`Test State`では、イベント無し・イベント前・アップデート待ち・イベント中・イベント後を切り替えられます。

ルートの`scripts/`はリポジトリ内のどこからでも実行でき、必要な処理を
`app-update-landing-server/`を作業ディレクトリとして起動します。
