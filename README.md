# App Update Landing

UnityクライアントとUpdateランディングページ用サーバーを同じリポジトリで管理します。

```text
app-update-landing/
├── app-update-landing-unity/   # Unityプロジェクト
├── app-update-landing-server/  # PHPサーバー
└── scripts/                    # サーバーのpreview・deploy・releaseコマンド
```

サーバーの仕様、開発方法、ローカルプレビューについては
[サーバーREADME](app-update-landing-server/README.md)を参照してください。

## Unityパッケージ

Unityクライアントは、UPMパッケージ
[`com.harapeco.app.update.landing`](app-update-landing-unity/Packages/com.harapeco.app.update.landing/README.md)として管理します。
公開APIからイベント状態を取得し、Hostアプリの表示・詳細ダイアログ・ランディングページ遷移へ接続できます。

Unity Package Managerの `Add package from git URL...` で導入できます。

```text
git@Harapp-GitHub:Harapp/app-update-landing.git?path=app-update-landing-unity/Packages/com.harapeco.app.update.landing/
```

Unityパッケージのバージョン更新は、Unity Editorの
`Window > App Update Landing > Release`から確認できます。Releaseは全EditMode Test成功後に
`package.json`と`AppUpdateLandingVersion.Value`を同期更新しますが、commit・push・tag作成は行いません。

ルートの`scripts/`はリポジトリ内のどこからでも実行でき、必要な処理を
`app-update-landing-server/`を作業ディレクトリとして起動します。
