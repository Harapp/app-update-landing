# デプロイ方針

## 概要

Deployerを使用し、ゲームごとに任意のSSH接続可能なサーバーとディレクトリへデプロイします。

共通コードは1つのリポジトリで管理し、デプロイ対象ゲームに対応するJSONとテーマだけを選択して配置します。

## PurrfectSpirits本番デプロイ

Issue #8では、coreserverのSSH configに登録された`coreserver` aliasだけを使用します。接続ユーザー、秘密鍵、パスワードはリポジトリへ保存しません。
以下のコマンドはリポジトリルートで実行します。

```bash
composer --working-dir=app-update-landing-server install
scripts/release-purrfect-spirits
```

`scripts/release-purrfect-spirits`の実行自体を本番デプロイの承認として扱います。このゲーム名付きコマンドは、共通の`scripts/release-event-update`へゲームkeyとDeployer hostを渡す薄いラッパーです。共通スクリプトは未コミット差分がないことを確認し、対象commitと本番環境を表示したうえで、確認プロンプトを挟まずデプロイを開始します。成功時は最後に公開ページURLとバナーURLを表示します。実行計画だけを見る場合は`scripts/release-purrfect-spirits --plan`を使用します。

リリーススクリプトは低レベルの`scripts/deploy-purrfect-spirits`を呼び出します。Deployerの最初のタスクとして、ローカルで`composer test`、`composer validate:config`、`composer smoke`を実行します。配布元は実行時のGitアーカイブで、`bin`、Composer定義、固定ゲーム設定、公開コード、アプリケーションコード、テンプレートだけを許可リストで選択します。`.claude`、ドキュメント、テスト、既定ゲーム設定など、本番実行に不要なファイルはアーカイブへ含めず、サーバーへ転送しません。

ゲーム画像は`games/purrfect-spirits/assets/`へPNGまたはJPEGで保存します。デプロイ時にローカルの`cwebp`で品質82のWebPへ変換し、生成物を`public/assets/`としてリリースへ追加してからサーバーへ転送します。サーバー側に画像変換ツールは不要です。`banner.png`、`banner.jpg`、`banner.jpeg`はいずれも`/assets/banner.webp`になりますが、同じ出力名になる元画像を複数置くことはできません。

ローカルで生成結果を確認する場合は次を実行します。生成物は`build/purrfect-spirits/public/assets/`へ出力され、Git管理しません。

```bash
composer --working-dir=app-update-landing-server assets:build
```

リリース実行環境には`cwebp`が必要です。macOSではHomebrewの`webp` formulaなどで用意します。元画像がない場合や`banner.webp`を生成できない場合、デプロイは公開リンクを切り替える前に失敗します。

転送後はまだ`current`を切り替えず、候補release上でComposerのplatform要件、PHP構文、JSON Schemaと固定ゲーム設定、iOS・Android・PCの更新先とHTML生成を検証します。すべて成功した場合だけ`current`と公開リンクを切り替え、その後に公開ページ・バナー・公開APIのHTTPS health checkを実行します。公開ルートとリリース対象版は`games/{game-key}/update-pages.json`を正とし、相対画像パスの解決、health check、完了時URL表示で共用します。

Deployerのリリース領域は公開ディレクトリ外に置きます。

```text
/home/harapeco/domains/neko.harapeco.okinawa/.deploy/event-update/
├── releases/<release>/
└── current -> releases/<release>
```

公開URLのパスは`current/public`へのシンボリックリンクとして接続します。

```text
/home/harapeco/domains/neko.harapeco.okinawa/public_html/event-update
  -> /home/harapeco/domains/neko.harapeco.okinawa/.deploy/event-update/current/public
```

事前確認では、coreserverのPHP 8.3.20、Composer、Git、rsync、配置先の書込み権限、シンボリックリンク利用可を確認済みです。既存の公開パスが空の通常ディレクトリであれば初回デプロイ時にリンクへ置換します。内容のある通常ディレクトリは削除せず、デプロイを失敗させて公開中の状態を維持します。

公開リンク切替後にHTTPS health checkを行い、失敗時は直前リリースへ`rollback`して公開リンクを同期します。候補release上の検証に失敗した場合は`current`を切り替えません。手動ロールバックは次のコマンドです。

```bash
cd app-update-landing-server
vendor/bin/dep rollback coreserver
```

本番デプロイ対象は`coreserver`のみで、staging hostは定義していません。

## デプロイ単位

1つのデプロイ先は1つのゲームと1つの環境に対応します。

```text
game-a-staging
game-a-production
game-b-staging
game-b-production
```

同じ物理サーバー内でも、異なる`deploy_path`を指定できます。

```text
/var/www/app-update-landing/
├── game-a/
│   ├── staging/
│   └── production/
└── game-b/
    ├── staging/
    └── production/
```

## Deployerの役割

- SSHによる接続
- デプロイ対象ゲームと環境の選択
- デプロイ先ディレクトリの作成
- PHPコード、テンプレート、静的ファイル、対象ゲーム設定の転送
- Composer依存関係の準備
- デプロイ前のテストとJSON Schema検証
- リリースディレクトリの作成
- `current`シンボリックリンクの切り替え
- 古いリリースへのロールバック
- 古いリリースの世代管理

## サーバー構成例

```text
/var/www/game-a-update/
├── releases/
│   ├── 20260903120000/
│   └── 20260910100000/
├── current -> releases/20260910100000
└── .dep/
```

LiteSpeedのDocument Rootは`current/public`へ向けます。

```text
game-a.update.example.com
  → /var/www/game-a-update/current/public
```

## ゲームの識別

ゲーム識別子を利用者から受け取りません。デプロイ時に対象ゲームの設定を選択し、配布物へ固定します。

```text
game-a.update.example.com
  → Game A設定だけを含む

game-b.update.example.com
  → Game B設定だけを含む
```

これにより、誤った`game` parameterによる別ゲーム表示、キャッシュ混在、設定漏えいを防ぎます。

## デプロイ対象の定義

`deploy.php`またはinventoryには、次の情報を持たせます。

- Deployer上のhost alias
- SSH接続先hostname
- SSHユーザー
- `deploy_path`
- 対象ゲームのkey
- `staging`または`production`などの環境label
- サーバー上のPHP実行コマンド

秘密鍵、パスワード、tokenはリポジトリへ保存しません。SSH接続情報は可能な限り実行環境のSSH configまたはCIのsecretで管理します。

## 運用コマンド

coreserver本番だけを対象にし、selectorを省略せず明示します。

```bash
scripts/release-purrfect-spirits
scripts/deploy-purrfect-spirits --plan
scripts/release-event-update purrfect-spirits coreserver
scripts/deploy-event-update purrfect-spirits coreserver --plan
cd app-update-landing-server && vendor/bin/dep rollback coreserver
```

ゲーム名付きスクリプトにはゲーム固有のkeyとhostだけを置き、引数検証、clean worktree確認、Deployer起動などの共通処理は`release-event-update`と`deploy-event-update`へ集約します。

複数対象への一括デプロイは可能ですが、初期運用では誤配布を防ぐためゲームと環境を明示して実行します。

## デプロイフロー

```text
対象ゲームと環境を選択
  ↓
PHP test
  ↓
JSON Schema validation
  ↓
ゲーム画像をWebPへ変換
  ↓
対象ゲームだけを含む配布物を準備
  ↓
SSHまたはrsyncで新しいreleaseへ転送
  ↓
サーバー上の構成確認
  ↓
current symlinkを切り替え
  ↓
HTTP health check
  ↓
公開ページURL、バナーURL、API URLを表示
```

テスト、Schema検証、配布物作成に失敗した場合は、`current`を切り替えません。切り替え後のhealth checkに失敗した場合は、直前のreleaseへ戻せるようにします。

## サーバー要件

- SSH接続が許可されている
- デプロイユーザーが`deploy_path`へ書き込める
- LiteSpeedから`current/public`を読み取れる
- シンボリックリンクを利用できる
- PHP 8.3以上とIntl拡張を実行できる
- 必要なPHP extensionが有効である
- HTTPSを利用できる

Gitが利用できないサーバーでは、Deployerのrsync転送を使用できます。

## JSONの扱い

ゲーム設定はreleaseへ含め、サーバー上で直接編集しません。これにより、コードと設定を同じ単位で履歴管理し、ロールバックできます。

運用中にサーバー上で変更されるファイルが将来追加された場合だけ、Deployerのshared fileまたはshared directoryとして分離します。

## ドメイン変更

ゲームを別サーバーへ移動する場合も、公開ドメインを維持してDNSまたはLiteSpeedの向き先を変更します。Unityに設定する固定Update URLを変更せず、インフラだけを移行できる状態を目指します。

## 参照

- [Deployer Hosts](https://deployer.org/docs/8.x/hosts)
- [Deployer Basics](https://deployer.org/docs/8.x/basics)
- [Deployer rsync recipe](https://deployer.org/docs/7.x/contrib/rsync)
