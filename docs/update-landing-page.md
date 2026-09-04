# Updateランディングページ要件

NativeNotifydのUpdate通知をタップした利用者へ表示する、顧客向けWebページの要件です。
通知入力画面やUnity内のUIではなく、固定Update URLの遷移先となるWebページを対象とします。

## 目的

- 更新が必要な利用者をOSに対応した更新先へ案内する
- 更新済み、期間外、OS非対応の場合も、何も表示されない状態にせず理由を伝える
- Update URLを固定し、JSONの`releaseTargetVersion`に対応する最新のバナー、説明文、期間、遷移先を表示する
- Unity packageには更新可否の業務判断を持たせず、Webページ側へ集約する

## ページ構成

ページは次の要素で構成します。

1. バナー画像1枚
2. イベントの短い説明文1件
3. 更新不可の場合に表示する状態案内テキスト
4. 更新可能な場合だけ表示するボタン1件
5. 必要な場合だけ表示する小さな注意書き

```text
┌─────────────────────────┐
│                         │
│        バナー画像         │
│                         │
├─────────────────────────┤
│ イベントの短い説明文        │
│ 必要な場合だけ状態案内       │
│  [ V2.9.0 ]            │
│  [ Update and play... ]│
│ 薄いグレーの注意書き         │
└─────────────────────────┘
```

ボタンは1ページにつき最大1件とし、OSに対応する更新先を開きます。
対象バージョンは`V{targetVersion}`の形式で表示し、`V`は表示時だけ付けます。

## URL契約

Unityは固定Update URLへ、タップ時点の情報をquery parameterとして付与します。

```text
https://updates.example.com/update
  ?appVersion=1.4.2
  &locale=en-US
  &platform=ios
  &osVersion=18.1
```

| parameter | 必須 | 内容 |
| --- | --- | --- |
| `appVersion` | 任意 | タップ時点でインストールされているアプリバージョン。未指定時は更新済み判定を省略 |
| `locale` | 任意 | タップ時点の言語・ロケール。未指定時は`en` |
| `platform` | 任意 | `ios`、`android`、または`pc`。端末判定不能時のフォールバック |
| `osVersion` | 任意 | タップ時点のOSバージョン。未指定時は最低OS判定を省略 |

query parameterは表示判定の入力であり、認証・認可や秘密情報の受け渡しには使用しません。
端末ID、通知トークン、認証情報はURLへ含めません。
指定されたparameterだけを対応する判定に使用し、parameterが欠けていること自体はエラーにしません。指定値が不正な場合は推測で補正せず拒否します。
旧URLに`targetVersion`が残っていても値は使用せず、常にJSONの`releaseTargetVersion`を表示します。

platformはUser-Agentによる内部判定を優先します。iOSまたはAndroidを判定できた場合はquery parameterより内部判定を使用し、判定できない場合だけ有効な`platform`を使用します。どちらもない場合は`pc`とします。

ゲームはドメインまたはデプロイ先で固定し、ゲーム識別子をquery parameterとして受け取りません。

## 公開API

固定URLの`/api/`で、現在のリリース情報を認証なしのJSONとして提供します。返す情報は`releaseTargetVersion`に対応する設定のうち、リリース版、`enabled`、開始・終了日時、イベント状態、iOS・Android・PC別の`released`と遷移先URLだけです。

```json
{
  "schemaVersion": 1,
  "releaseVersion": "2.9.0",
  "enabled": true,
  "eventPeriod": {
    "startAt": "2026-09-05T00:00:00+09:00",
    "endAt": "2026-10-04T23:59:59+09:00",
    "phase": "upcoming"
  },
  "platforms": {
    "ios": {
      "released": true,
      "targetUrl": "https://apps.apple.com/example"
    }
  }
}
```

`eventPeriod.phase`は開始前を`upcoming`、期間中を`active`、終了後を`ended`とします。APIはGET・HEADだけを許可し、CORSは公開情報として全originを許可します。エラー時は内部情報を含めず、固定エラーコードを返します。

## 言語

対応言語、ロケールキー、文字方向、AI向け翻訳依頼プロンプトは[翻訳ガイド](translations.md)を正とします。

- 既定言語は `en` 固定とする
- 初期リリースでは英語文言を必須とする
- `en` は削除や別言語への変更を不可とする
- `locale` に一致する翻訳が将来追加された場合は、その翻訳を優先する
- 完全一致する翻訳がない場合は言語部分を確認し、最終的に必ず `en` へフォールバックする
- イベント説明、SNSカード、画像alt、状態メッセージ、ボタン文言は同じ言語解決規則を使う
- 状態メッセージ、ボタン文言、注意書きは`templates/event-update/ui-texts.json`へ、意味を表すキー単位で保存する
- ArabicとHebrewは`dir="rtl"`、それ以外は`dir="ltr"`で表示する
- RTL文中のバージョン番号はLTRとして分離し、日付と複数形はlocaleに合わせる

初期状態のイベント固有文言は次の形です。

```json
{
  "title": {
    "en": "The latest event is here."
  },
  "descriptions": {
    "en": "A new version is available for the latest event."
  },
  "socialCard": {
    "title": {
      "en": "Latest Event Update"
    },
    "description": {
      "en": "Update the app and play the latest event."
    }
  },
  "imageAltTexts": {
    "en": "Latest event update"
  }
}
```

## Updateページ設定

ファイル直下に公開ルートとリリース対象版を持ち、`pages`内に各`targetVersion`の表示設定を保持します。

```json
{
  "publicBaseUrl": "https://game-a.update.example.com/event-update",
  "releaseTargetVersion": "2.0.0",
  "pages": [
    {
      "targetVersion": "2.0.0",
      "template": "event-update",
      "enabled": true,
      "imageUrl": "assets/banner.webp",
      "startAt": "2026-09-10T00:00:00+09:00",
      "endAt": "2026-09-30T23:59:59+09:00",
      "released": {
        "ios": true,
        "android": false,
        "pc": true
      },
      "minimumOsVersions": {
        "ios": "18.0",
        "android": "14"
      },
      "destinationUrls": {
        "ios": "https://apps.apple.com/app/id000000000",
        "android": "https://play.google.com/store/apps/details?id=com.example.app",
        "pc": "https://example.com/download"
      },
      "title": {
        "en": "The latest event is here."
      },
      "descriptions": {
        "en": "A new version is available for the latest event."
      },
      "socialCard": {
        "title": {
          "en": "Latest Event Update"
        },
        "description": {
          "en": "Update the app and play the latest event."
        }
      },
      "imageAltTexts": {
        "en": "Latest event update"
      }
    }
  ]
}
```

`template`は任意とし、未設定の場合は`event-update`を使用します。利用可能なテンプレートはサーバー側の許可リストで管理します。

`startAt`と`endAt`はそれぞれ任意です。両方未設定の場合は、`enabled=true`の間は期間制限なしで有効とします。
通常のアプリ更新では終了期限がない場合があるためです。

`minimumOsVersions`もplatformごとに任意とし、PCを含め、値がないplatformには最低OS制限を適用しません。
`released`はiOS・Android・PCそれぞれの配信状態を必須booleanで保持します。PCはサイトなどの遷移先が利用可能になった時点でtrueにします。
`title`はページ内の見出し、`descriptions`は見出しに続く説明文として表示します。どちらも`en`を必須とします。
`socialCard.title`と`socialCard.description`はSNSカード専用文言です。どちらも`en`を必須とし、ページ本文の`descriptions`とは分けて管理します。

## テンプレート

- 初期テンプレートは`event-update`とする
- 期間の有無はテンプレート選択に影響させない
- テンプレートは表示だけを担当し、期間やバージョンの判定を行わない
- テンプレート名は許可リストから解決し、任意のファイルパスとして使用しない
- 未知のテンプレート名は設定不正として扱う

## 期間判定

- 判定には端末時刻ではなくWebサーバーの現在時刻を使う
- 保存と比較はUTCに統一する
- 開始日時と終了日時を期間に含む

```text
startAt <= now <= endAt
```

- `startAt` 未設定: 即時開始
- `endAt` 未設定: 無期限
- 両方未設定: `enabled=true`の間は常時有効
- `now < startAt`: 期間前
- `endAt < now`: 期間終了

## 表示判定

判定は次の優先順で行います。

| 優先順 | 条件 | 表示状態 | ボタン |
| --- | --- | --- | --- |
| 1 | 指定されたparameterが不正、または`releaseTargetVersion`に対応する設定がない | ページを表示できない | 非表示 |
| 2 | `enabled=false` | 現在利用できない | 非表示 |
| 3 | `appVersion`が指定され、`appVersion >= targetVersion` | 更新済み | 非表示 |
| 4 | `endAt < now` | 期間終了 | 非表示 |
| 5 | 未対応の`platform` | 更新不可 | 非表示 |
| 6 | `osVersion`が指定され、最低対応バージョン未満 | OS非対応 | 非表示 |
| 7 | 対応する更新先URLがない | 一時的に更新不可 | 非表示 |
| 8 | `released[platform]=false` | 未配信 | 「Coming Soon」を無効表示 |
| 9 | 上記以外 | アップデート可能 | 表示 |

更新済み判定を期間判定より先に行います。更新後に古い通知をタップした利用者には、期間終了ではなく最新版を利用中であることを伝えます。

更新可能状態はUpdateボタンで明示し、独立した状態案内テキストは表示しません。更新不可や設定不正の場合は空欄にせず、利用者向けの理由を表示します。

## 状態別表示

システム文言の初期値も英語固定とします。

### アップデート可能

- バナー画像
- イベント説明
- 開始前は開始までの日数、期間中は残り日数を表示する
- ボタンは1段目に`V2.9.0`、2段目に開始前なら`Update and get ready for the event`、期間中なら`Update and play the event`などのローカライズ済み行動文言を表示する
- 状態文、現在バージョンと対象バージョンの補助行は表示しない
- ボタン押下時は`platform`に対応する`destinationUrls`を開く
- iOS / Androidでは、ボタンの下にストア反映の遅延に関する小さな注意書きを表示する

### 更新済み

- バナー画像
- イベント説明
- `You're using the latest version.`
- 更新ボタンは表示しない

### 未配信

- バナー画像
- イベント説明
- 開始前は`Event period: Sep 10–30 (starts in 7 days)` / `イベント期間：9月10日〜30日（7日後に開始）`形式の期間と開始までの日数
- 通常ボタンと同じ位置・サイズで、`Coming Soon` / `近日開始`の無効ボタンを表示する
- 無効ボタンから更新先へは遷移せず、その下にストア反映の注意書きを表示する

### 期間終了

- バナー画像
- イベント説明
- `Event period: Ended.` / `イベント期間：終了しました。`
- 更新ボタンは表示しない

### OS非対応

- バナー画像
- イベント説明
- `This update requires a newer OS version.`
- 現在のOSバージョンと最低対応OSバージョンを表示する
- 更新ボタンは表示しない

### 設定不正・取得失敗

- バナーを取得できる場合は表示する
- `This update page is currently unavailable.`
- 更新ボタンは表示しない
- 内部エラー、設定値、stack traceは利用者へ表示しない

## バナー画像

- `imageUrl`はゲーム設定の`publicBaseUrl`を基準とする相対WebPパスを基本とする
- 外部配信する場合だけ、許可されたホストを持つ絶対HTTPS URLも使用できる
- 相対パスは先頭の`/`、`.`、`..`を許可せず、公開ルート外を参照できない形式に限定する
- 元画像はPNGまたはJPEGで管理し、リリース時にWebPへ変換する
- 画面幅に追従し、縦横比を維持する
- 画像取得に失敗しても、説明文と状態案内を表示する
- `imageAltTexts.en` を必須とする
- 初期仕様では全言語で1つの`imageUrl`を使用する
- バナー内には翻訳が必要な文章を埋め込まないことを推奨する

## ボタンと遷移

- ボタンはアップデート可能な場合だけ表示する
- ボタンの表示は2段とし、1段目に`V{targetVersion}`、2段目にローカライズされた行動文言を表示する
- 開始前の英語初期値は`Update and get ready for the event`、日本語初期値は`更新してイベントに備える`とする
- 期間中の英語初期値は`Update and play the event`、日本語初期値は`更新してイベントを遊ぶ`とする
- 期間終了後はボタンを表示しない
- ボタンのアクセシブルな名前にも対象バージョンと期間状態に対応する行動文言を含める
- ボタンはコンテンツ内で中央寄せとし、モバイルでも押しやすい幅と高さを確保する
- 内部判定で解決したplatformが`ios`ならiOS用URL、`android`ならAndroid用URLを使用する
- 解決したplatformが`pc`ならPC用の案内、ストア、またはダウンロードURLを使用する
- PC用URLは特定ストアへ固定せず、Updateページ設定で任意のHTTPS URLを指定できるようにする
- 遷移先は事前設定されたHTTPS URLに限定し、query parameterから任意の遷移先を受け取らない
- ボタンの連打による多重遷移を防ぐ
- 遷移できない場合はページ内で安全なエラー文言を表示する

## ストア反映に関する注意書き

`platform`が`ios`または`android`の場合は、配信済み・未配信のどちらでもページの一番下に次の注意書きを表示します。

```text
Updates may take some time to appear on the App Store or Google Play. If the update is not available yet, please try again later.
```

- 既定言語は`en`とし、ほかのページ文言と同じ言語解決規則を使う
- ボタンより視覚的な優先度を下げ、小さめの文字とする
- 文字色は十分なコントラストを保った薄めのグレーとする
- 読めないほど薄い色や小さい文字にはせず、本文より小さくても十分なコントラストを確保する
- PCでは表示しない
- 更新済み、期間終了、OS非対応など、更新CTA自体がない状態では表示しない

## バージョン比較

- `appVersion`、`osVersion`の許容形式を明示し、Server・Unity・Webで同じ規則を使う
- バージョンparameterは任意とし、指定された値にだけ形式検証と対応する判定を適用する
- 初期仕様では`1.2.3`のような数値をピリオドで区切った形式に限定する
- 数値として各segmentを比較し、文字列の辞書順では比較しない
- `1.2`と`1.2.0`の扱いを統一し、初期仕様では同一として扱う
- 不正な形式は推測で補正せず、更新不可またはページ利用不可として扱う

## セキュリティとプライバシー

- ページ全体をHTTPSで提供する
- query parameterと設定値を信頼せず、長さ・形式・許容値を検証する
- 表示時にすべての外部入力をescapeし、XSSを防ぐ
- 解決後の`imageUrl`と更新先URLにhost allowlistを適用する
- 任意URLへ転送できるopen redirectを作らない
- Content Security Policyを設定し、画像・script・遷移先を必要なoriginへ限定する
- 個人識別子、通知トークン、credentialを受け取らず、ログにも記録しない
- query parameterだけで権限や限定コンテンツの閲覧可否を決めない
- 検索結果への掲載とキャッシュを避けるため`noindex, nofollow, noarchive`を返すが、SNSカード用クローラーを含むbotのアクセス自体は一律遮断しない

## UI・アクセシビリティ

- モバイル表示を優先する
- バナー、説明文、必要な場合の状態案内テキスト、ボタン、注意書きの順序を固定する
- ボタンは十分なタップ領域とコントラストを持つ
- 画像には解決後の言語に対応するaltを設定する
- 状態を色だけで表現しない
- 読み込み中、画像失敗、設定取得失敗でもレイアウトが大きく崩れない

## SNSカード

- Open GraphとX（Twitter）のLarge Image Cardに対応する
- カード画像にはページ内のバナー画像と同じ絶対HTTPS URLを使う
- カードのタイトルと説明、画像altは、ページで解決された言語の文言を使う
- カードタイトルと説明は`socialCard`で明示し、ページ本文の説明を流用しない

## テスト要件

- 更新可能、更新済み、未配信、期間終了、OS非対応、platform非対応をそれぞれ確認する
- iOS / Androidの内部判定が共有URL上の異なるplatformを上書きし、platform未指定時にも機能することを確認する
- `startAt`と`endAt`の境界時刻を確認する
- 期間設定がない場合、開始日時だけの場合、終了日時だけの場合を確認する
- `1.2`、`1.2.0`、`1.10.0`など、文字列比較で誤りやすいバージョンを確認する
- `en-US`、未対応locale、不正localeが`en`へフォールバックすることを確認する
- バナー取得失敗時にも説明文と状態案内が表示されることを確認する
- iOS、Android、PCで正しい更新先が選択されることを確認する
- ボタンに`V{targetVersion}`とローカライズされた行動文言が2段で表示されることを確認する
- 開始前は「イベントに備える」、期間中は「イベントを遊ぶ」、終了後はボタン非表示になることを確認する
- 更新可能状態で状態文と現在・対象バージョンの補助行が表示されず、期間と残り日数が表示されることを確認する
- 期間前は開始までの日数、未配信platformは無効な`Coming Soon`ボタン、期間終了後は終了文言が表示されることを確認する
- `ja`と`ja-*`で日本語、未対応localeで`en`へフォールバックすることを確認する
- `ar`と`he`でRTL表示、翻訳済み本文、ローカライズ済み日付、適切な複数形になることを確認する
- iOS / Androidの配信済み・未配信状態で、ページ最下部にストア反映の注意書きが表示されることを確認する
- 不正なquery parameter、`releaseTargetVersion`に対応する設定の欠落、無効な外部URLを安全に拒否する
- query parameterを省略しても、既定言語、内部platform判定、`releaseTargetVersion`によりページを表示できることを確認する
- 未指定のテンプレートが`event-update`へ解決され、未知のテンプレートが安全に拒否されることを確認する
- Consoleから表示するテストリンクで、固定URLと現在の`releaseTargetVersion`のページを確認できる

## 対象外

- 通知の作成・送信画面
- Unity package内での更新可否判定
- Update通知の配信対象抽出
- App Store / Google Playの公開状態取得
- 強制アップデートによるアプリ操作のブロック
- `releaseId`の導入
