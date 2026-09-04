# App Update Landing

複数のゲームやアプリで共通利用できる、アプリアップデート案内ページの基盤です。

通知などから固定URLを開いた利用者に対して、現在のアプリバージョン、対象バージョン、OS、言語、公開期間を判定し、適切な案内と更新先を表示します。

## 現在の状態

設計段階です。実装はまだ開始していません。

## 目的

- 更新可能な利用者をOSに対応したストアまたはダウンロードページへ案内する
- 更新済み、期間外、OS非対応、設定不正の場合も理由を表示する
- ゲームごとの差分をJSON設定とテーマへ分離する
- 判定処理とテンプレートを複数ゲームで共通利用する
- 任意のSSH接続可能なサーバーへゲーム単位でデプロイできるようにする

## 技術方針

- PHP 8.3以上
- PHP Intl拡張
- LiteSpeedなどの一般的なPHP実行環境
- フレームワークを使用しない軽量な構成
- ComposerによるPSR-4オートロード
- Git管理されたJSONによる設定
- JSON Schemaによる設定検証
- サーバーサイドでのHTML生成
- 初期実装ではブラウザ側JavaScriptを使用しない
- DeployerによるSSHデプロイ

MariaDBやCMSは初期実装では使用しません。

## 基本構成

```text
Unityなどの呼び出し元
  ↓ query parameter付き固定URL
LiteSpeed
  ↓
PHP
  ├─ 対象ゲームのJSON設定を読み込む
  ├─ 期間、バージョン、OS、言語を判定する
  ├─ 表示用データを生成する
  └─ 許可されたテンプレートでHTMLを生成する
  ↓
利用者へUpdateランディングページを表示
```

1つのデプロイ先は1つのゲームに固定します。ゲーム識別子をquery parameterとして受け取りません。

```text
game-a.update.example.com
  → Game A用のデプロイ先とJSON

game-b.update.example.com
  → Game B用のデプロイ先とJSON
```

## ドキュメント

- [Updateランディングページ要件](docs/update-landing-page.md)
- [実装方針](docs/implementation.md)
- [デプロイ方針](docs/deployment.md)
- [翻訳ガイド・AI翻訳依頼プロンプト](docs/translations.md)

## 想定ディレクトリ

```text
app-update-landing/
├── config/
│   └── schema/
├── games/
│   └── {game-key}/
│       ├── assets/
│       │   └── banner.png
│       ├── update-pages.json
│       └── theme.json
├── public/
│   ├── index.php
│   └── assets/
├── src/
├── templates/
│   ├── event-update.php
│   └── event-update/
│       └── ui-texts.json
├── tests/
├── deploy.php
├── scripts/
│   ├── deploy-event-update
│   └── release-event-update
└── composer.json
```

`games/{game-key}/assets/`にはPNGまたはJPEGの元画像を置きます。リリース時に同じ相対パスとファイル名でWebPへ変換され、`public/assets/`から配信されます。たとえば`games/purrfect-spirits/assets/banner.png`は`/assets/banner.webp`になります。元画像は直接公開しません。

この構成は実装時に確定します。

## 初期スコープ

- `event-update`テンプレート
- iOS、Android、PCへの案内
- 英語を必須とする多言語表示
- 任意の開始日時と終了日時
- アプリバージョンとOSバージョンの判定
- 状態に応じた案内テキスト
- ゲーム単位のテーマとUpdateページ設定
- ゲーム単位のデプロイとロールバック

通知送信、配信対象抽出、Unity内での更新制御、強制アップデートは対象外です。
