# 実装方針

## 概要

App Update Landingは、query parameterとゲーム別JSON設定から表示状態を判定し、顧客向けのHTMLをサーバーサイドで生成します。

初期実装はPHPフレームワークを使用しません。ただし、処理を責務ごとに分け、将来テンプレートやゲームが増えても判定ロジックを複製しない構成にします。

詳細な画面要件と判定順は[Updateランディングページ要件](update-landing-page.md)を正とします。

## 設計原則

- 1つのデプロイ先は1つのゲームだけを扱う
- ゲームはドメインまたはデプロイ設定で確定する
- `game`をquery parameterとして受け取らない
- JSONはGitで管理し、デプロイ対象に含める
- 期間判定にはWebサーバーのUTC時刻を使用する
- query parameterとJSON設定はどちらも信頼せず検証する
- 判定ロジックをPHPテンプレートへ書かない
- テンプレート名を任意のファイルパスとして扱わない
- 利用者には必ず安全な状態メッセージを返す
- 内部エラーや設定値を利用者へ表示しない

## リクエスト処理

```text
HTTP Request
  ↓
Request parser / validator
  ↓
Update page repository
  ↓
State evaluator
  ↓
Localized view model
  ↓
Template registry
  ↓
Escaped HTML response
```

### 1. リクエスト解析

次のquery parameterを受け取ります。

| parameter | 内容 |
| --- | --- |
| `appVersion` | インストール済みアプリバージョン |
| `targetVersion` | 案内対象アプリバージョン |
| `locale` | 端末のロケール |
| `platform` | `ios`、`android`、`pc` |
| `osVersion` | 端末のOSバージョン |

入力値は長さ、形式、許容値を検証します。端末ID、通知トークン、認証情報は受け取りません。

### 2. 設定取得

デプロイ時に固定されたゲームの`update-pages.json`から、`targetVersion`に一致する設定を1件取得します。

初期仕様では、同じゲーム内の`targetVersion`を一意にします。一致する設定がない場合や複数存在する場合は、設定不正として扱います。

### 3. 状態判定

状態判定は要件で定義された優先順を厳守します。代表的な状態は次のとおりです。

- `unavailable`
- `disabled`
- `up-to-date`
- `not-started`
- `ended`
- `unsupported-platform`
- `unsupported-os`
- `missing-destination`
- `available`

判定結果は文字列だけでなく、画面表示に必要な値を持つView Modelへ変換します。

### 4. 言語解決

ロケールは次の順で解決します。

```text
完全一致
  ↓ 見つからない
言語部分
  ↓ 見つからない
en
```

例として、`ja-JP`に完全一致する設定がなければ`ja`を確認し、さらに存在しなければ`en`を使用します。`en`は必須です。

### 5. HTML生成

判定済みのView Modelをテンプレートへ渡します。テンプレートは条件判定や設定取得を行わず、表示だけを担当します。

すべての文字列をHTML escapeし、URLは検証済みの値だけを使用します。

## PHPクラスの責務

実装時のクラス名は調整できますが、責務は次の単位に分割します。

| クラス | 責務 |
| --- | --- |
| `UpdatePageRequest` | query parameterの取得と入力状態の保持 |
| `RequestValidator` | リクエスト値の形式と許容値の検証 |
| `UpdatePageRepository` | JSONの読み込みと`targetVersion`による取得 |
| `UiTextRepository` | UI翻訳JSONの検証と読み込み |
| `UpdatePageConfigValidator` | JSON設定の実行時検証 |
| `Version` | 数値segmentによるバージョン比較 |
| `LocaleResolver` | ロケール解決と`en`フォールバック |
| `UpdatePageEvaluator` | 表示状態の判定 |
| `UpdatePageViewModel` | テンプレートへ渡す判定済みデータ |
| `TemplateRegistry` | 許可されたテンプレート名とファイルの対応管理 |
| `HtmlRenderer` | テンプレートによるHTMLレスポンス生成 |
| `Clock` | テスト可能な現在時刻の提供 |

`Clock`を抽象化し、期間境界のテストで現在時刻を固定できるようにします。

## JSON設定

ゲームごとに設定ファイルを分離します。

```text
games/
├── game-a/
│   ├── update-pages.json
│   ├── theme.json
│   └── ui-texts.json
└── game-b/
    ├── update-pages.json
    ├── theme.json
    └── ui-texts.json
```

`update-pages.json`の想定例です。

```json
{
  "pages": [
    {
      "targetVersion": "2.2.0",
      "template": "event-update",
      "enabled": true,
      "imageUrl": "assets/banner.webp",
      "startAt": "2026-09-10T00:00:00Z",
      "endAt": "2026-10-10T00:00:00Z",
      "minimumOsVersions": {
        "ios": "18.0",
        "android": "14"
      },
      "destinationUrls": {
        "ios": "https://apps.apple.com/app/id000000000",
        "android": "https://play.google.com/store/apps/details?id=com.example.app",
        "pc": "https://example.com/download"
      },
      "descriptions": {
        "en": "A new version is available for the latest event.",
        "ja": "最新イベント向けの新しいバージョンがあります。"
      },
      "imageAltTexts": {
        "en": "Latest event update",
        "ja": "最新イベントのアップデート"
      }
    }
  ]
}
```

### 期間の省略

`startAt`と`endAt`はそれぞれ任意です。

| 設定 | 意味 |
| --- | --- |
| 両方なし | `enabled=true`の間は常時有効 |
| `startAt`のみ | 指定日時から無期限 |
| `endAt`のみ | 即時開始し、指定日時で終了 |
| 両方あり | `startAt <= now < endAt`の期間だけ有効 |

期間の有無はテンプレート選択に影響させません。

### JSON Schema

JSON Schemaでは少なくとも次を検証します。

- `targetVersion`の形式
- `template`が許可された識別子であること
- `enabled`がbooleanであること
- `startAt`と`endAt`がUTCのISO 8601形式であること
- `en`の説明と画像altが存在すること
- platformが`ios`、`android`、`pc`のいずれかであること
- 画像が安全な相対WebPパス、または許可された絶対HTTPS URLであること
- 外部遷移URLが絶対HTTPS URLであること
- 未知のフィールドがないこと

### UI翻訳

状態文、ボタン、注意書きなどの共通UI翻訳は、ゲームごとの`ui-texts.json`で管理します。AIや翻訳担当者が文脈を把握しやすいよう、意味を表すキーごとに言語を並べます。

```json
{
  "button.update": {
    "en": "Update and play the event",
    "ja": "更新してイベントを遊ぶ"
  },
  "notice.storeDelay": {
    "en": "Updates may take some time to appear...",
    "ja": "アップデートが反映されるまで、時間がかかる場合があります..."
  }
}
```

すべての文言キーで`en`を必須とし、完全一致、言語部分、`en`の順で解決します。アクセシブルな名前などで使用する`{version}`等のplaceholderは、すべての翻訳に残す必要があります。

標準のJSON Schemaだけでは表現しにくい次の整合性は、デプロイ前の追加検証で確認します。

- 同じゲーム内で`targetVersion`が一意であること
- 両方指定された場合に`startAt < endAt`であること
- 相対画像パスをゲーム設定の`publicBaseUrl`へ解決できること
- 解決後の画像URLと更新先URLのhostが許可リストに含まれること

Schema検証と追加検証はデプロイ前に実行します。実行時にも、ページを安全に表示するために必要な検証をPHP側で行います。

## テンプレート

初期テンプレートは`event-update`の1種類です。

```text
templates/
└── event-update.php
```

JSONの`template`を直接`include`へ渡しません。PHP側で許可リストを定義します。

```php
$templates = [
    'event-update' => __DIR__ . '/../templates/event-update.php',
];
```

`template`が未指定の場合は`event-update`を使用します。未知のテンプレート名はフォールバックせず、設定不正として安全なページを表示します。

テンプレートは次のView Modelだけを受け取ります。

- 解決済みの言語
- バナー画像とalt
- イベント説明
- 状態メッセージ
- 表示可能な場合だけローカライズ済みのUpdateボタン
- 表示可能な場合だけローカライズ済みの注意書き
- ゲームテーマ

## テーマ

ゲーム固有の配色、ロゴ、最大表示幅などは`theme.json`に分離します。判定ロジックやHTML構造をテーマへ含めません。

テーマ値も許容形式と範囲を検証し、CSSへ出力する値を限定します。任意のCSS文字列を設定から挿入できる構造にはしません。

配色は`colorPreset`で次のプリセットから選択できます。

- `purple`: 紫を基調とした配色
- `red`: 赤を基調とした配色
- `blue`: 青を基調とした配色
- `green`: 緑を基調とした配色
- `orange`: オレンジを基調とした配色
- `pink`: ピンクを基調とした配色
- `gray`: グレーを基調とした配色

```json
{
  "colorPreset": "purple",
  "logoUrl": null,
  "maxContentWidth": 640
}
```

独自配色が必要な場合は、従来どおり`primaryColor`、`accentColor`、`backgroundColor`、`textColor`の4項目をすべて指定します。プリセットと独自配色は同時に指定できません。

`logoUrl`は任意表示です。PurrfectSpiritsの初期リリースではIconを表示しないため`null`とします。

## ゲーム画像の生成

バナーなどの元画像は`games/{game-key}/assets/`へPNGまたはJPEGで保存します。リリース処理がローカルでWebPへ変換し、同じ相対パスで`public/assets/`へ配置します。生成済みWebPはGit管理せず、コード、設定、生成画像を同じリリースとして配布・ロールバックします。

## HTTPとセキュリティ

- HTTPSを必須とする
- 更新先URLと画像URLへhost allowlistを適用する
- query parameterから遷移先URLを受け取らない
- Content Security Policyを設定する
- `X-Content-Type-Options: nosniff`を設定する
- 適切な`Referrer-Policy`を設定する
- 利用者向けレスポンスへstack traceを含めない
- URL全体をそのままアクセスログへ残さない運用を検討する
- 認証情報や端末識別子をログへ記録しない

## エラー処理

例外や設定不正が発生しても、可能な限りHTTPレスポンスとして安全な案内ページを返します。

利用者へは共通の利用不可メッセージだけを表示します。詳細はサーバーログへ記録しますが、query parameterの値は必要最小限とし、秘密情報や個人識別情報を記録しません。

## キャッシュ

初期実装では、正しさを優先して過度なページキャッシュを行いません。レスポンスは`appVersion`、`targetVersion`、`locale`、`platform`、`osVersion`、現在時刻に依存するためです。

性能上必要になった場合は、生成HTMLではなく解析済みJSON設定を短時間キャッシュします。キャッシュを導入しても期間境界の判定はリクエストごとに行います。

## テスト方針

### Unit test

- バージョン比較
- ロケールの完全一致、言語一致、`en`フォールバック
- 状態判定の優先順
- 期間の開始・終了境界
- 期間設定なし、開始のみ、終了のみ
- OS最低バージョン判定
- platform別の遷移先選択
- 不正なquery parameterとJSON設定
- テンプレート許可リスト

### Integration test

- query parameterから期待するHTMLが返ること
- 状態ごとにボタンの表示有無が正しいこと
- HTMLがescapeされること
- セキュリティヘッダーが付与されること
- 内部エラーが利用者へ露出しないこと

### Visual test

- モバイル幅を優先した表示
- 長い翻訳文言
- バナー画像取得失敗
- iOS、Android、PCの表示差
- キーボード操作とフォーカス表示
- 十分なコントラストとタップ領域

## 対象外

- JSONを編集する管理画面
- MariaDBやCMSへの保存
- 通知作成と送信
- Unity内での更新可否判定
- App StoreやGoogle Playの公開状態取得
- 強制アップデート
