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

ルートの`scripts/`はリポジトリ内のどこからでも実行でき、必要な処理を
`app-update-landing-server/`を作業ディレクトリとして起動します。
