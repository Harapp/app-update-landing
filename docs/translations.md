# 翻訳ガイド

このドキュメントは、Updateランディングページの翻訳対象、ロケールキー、翻訳時の制約をまとめた正本です。
AIへ翻訳を依頼する場合や、外部の翻訳担当者へ渡すプロンプトを作る場合は、このドキュメントを参照してください。

## 基本方針

- 原文と最終フォールバックは英語（`en`）とする
- LTR言語に加えて、Arabic（`ar`）とHebrew（`he`）は右から左（RTL）で表示する
- 翻訳対象は、下表の英語を除く31言語とする
- 既存翻訳は、明示的に校正または再翻訳を依頼された場合だけ変更する
- JSONの文言キー、階層、並び順、プレースホルダーを維持する

## 対応言語

対応言語は32言語です。`JSON locale`を`ui-texts.json`と`update-pages.json`の言語キーに使用します。

| # | Language | 日本語名 | JSON locale | 用途 |
| ---: | --- | --- | --- | --- |
| 1 | English | 英語 | `en` | 原文・必須フォールバック |
| 2 | Arabic | アラビア語 | `ar` | 翻訳対象・RTL |
| 3 | Italian | イタリア語 | `it` | 翻訳対象 |
| 4 | Indonesian | インドネシア語 | `id` | 翻訳対象 |
| 5 | Ukrainian | ウクライナ語 | `uk` | 翻訳対象 |
| 6 | Dutch | オランダ語 | `nl` | 翻訳対象 |
| 7 | Catalan | カタルーニャ語 | `ca` | 翻訳対象 |
| 8 | Greek | ギリシャ語 | `el` | 翻訳対象 |
| 9 | Swedish | スウェーデン語 | `sv` | 翻訳対象 |
| 10 | Spanish | スペイン語 | `es` | 翻訳対象 |
| 11 | Slovak | スロバキア語 | `sk` | 翻訳対象 |
| 12 | Slovenian | スロベニア語 | `sl` | 翻訳対象 |
| 13 | Thai | タイ語 | `th` | 翻訳対象 |
| 14 | Czech | チェコ語 | `cs` | 翻訳対象 |
| 15 | Danish | デンマーク語 | `da` | 翻訳対象 |
| 16 | German | ドイツ語 | `de` | 翻訳対象 |
| 17 | Turkish | トルコ語 | `tr` | 翻訳対象 |
| 18 | Norwegian | ノルウェー語 | `no` | 翻訳対象 |
| 19 | Hungarian | ハンガリー語 | `hu` | 翻訳対象 |
| 20 | Hindi | ヒンディー語 | `hi` | 翻訳対象 |
| 21 | Finnish | フィンランド語 | `fi` | 翻訳対象 |
| 22 | French | フランス語 | `fr` | 翻訳対象 |
| 23 | Vietnamese | ベトナム語 | `vi` | 翻訳対象 |
| 24 | Hebrew | ヘブライ語 | `he` | 翻訳対象・RTL |
| 25 | Polish | ポーランド語 | `pl` | 翻訳対象 |
| 26 | Portuguese | ポルトガル語 | `pt` | 翻訳対象 |
| 27 | Romanian | ルーマニア語 | `ro` | 翻訳対象 |
| 28 | Russian | ロシア語 | `ru` | 翻訳対象 |
| 29 | Korean | 韓国語 | `ko` | 翻訳対象 |
| 30 | ChineseSimplified | 中国語（簡体字） | `zh-Hans` | 翻訳対象 |
| 31 | ChineseTraditional | 中国語（繁体字） | `zh-Hant` | 翻訳対象 |
| 32 | Japanese | 日本語 | `ja` | 翻訳対象 |

中国語は簡体字と繁体字を区別するため、呼び出し元も`zh-Hans`または`zh-Hant`を明示してください。地域別の表現が必要になった場合だけ、`pt-BR`などの地域付きロケールを追加します。

## 翻訳対象ファイル

### 共通UI文言

[event-update UI文言](../templates/event-update/ui-texts.json)を翻訳します。

- 状態メッセージ
- Updateボタンとアクセシブルな名前
- Coming Soon
- ストア反映待ちの注意書き
- 期間、残り日数、開始までの日数
- OS要件

### ゲーム固有文言

各ゲームの`games/{game-key}/update-pages.json`にある次のフィールドを翻訳します。

- `title`
- `descriptions`
- `socialCard.title`
- `socialCard.description`
- `imageAltTexts`

PurrfectSpiritsの正本は[PurrfectSpirits Updateページ設定](../games/purrfect-spirits/update-pages.json)です。

`theme.json`、URL、バージョン、日時、配信フラグは翻訳対象ではありません。

## 翻訳ルール

- 英語の意味を原文として翻訳し、日本語など既存の翻訳は文脈参考として扱う
- ゲーム内UIとして短く、自然で、次の操作が分かる表現にする
- `PurrfectSpirits`、`App Store`、`Google Play`などの固有名詞は変更しない
- `V2.9.0`などのバージョン表記と数字を変更しない
- `{version}`、`{start}`、`{end}`、`{range}`、`{days}`を一字も変更せず、翻訳文内にすべて残す
- JSONとして有効な文字列を出力し、改行や引用符を適切にエスケープする
- 原文にない文言キーを作らず、依頼されたロケールだけを追加用データとして出力する
- Arabicは`one`、`two`、`few`、`many`、`other`、Hebrewは`one`、`two`、`other`の残日数・開始日数を自然な複数形にする。カウント値は最低1日のため`zero`は使用しない
- ArabicとHebrewの文字列へ、表示方向を制御するHTMLや不可視文字を埋め込まない

日付は日本語、Arabic、Hebrewに専用形式を使用し、それ以外の言語では英語の月略称を使用します。ArabicとHebrewの日付・数字はPHP Intlにより要求localeへ合わせて整形します。

## AIに翻訳依頼プロンプトを作らせる方法

AIへは次のように依頼してください。

```text
docs/translations.mdに従って、対象ファイルの未翻訳言語を翻訳担当者へ依頼するためのプロンプトを作ってください。
英語を原文とし、既存翻訳は変更対象から除外してください。
対象ファイル、対象ロケール、保持必須のプレースホルダー、期待するJSON出力形式をプロンプト内に含めてください。
```

AIは依頼プロンプトを作る前に、対象ファイルを読み、実際に不足しているロケールだけを列挙します。対象言語が指定されていない場合は、このドキュメントの31翻訳対象言語から、対象ファイルに存在する言語を除いたものを対象にします。`{{SOURCE_JSON}}`には、URLや日時などを含むファイル全体ではなく、翻訳対象フィールドと英語原文だけを抽出します。

翻訳結果は元ファイルへそのまま上書きする完全版ではなく、マージ用のJSONとして出力します。共通UI文言では`button.update`などの文言キー、ゲーム固有文言では`title`、`descriptions`、`socialCard`などの階層を維持し、その配下には依頼されたlocaleだけを含めます。

## 翻訳依頼プロンプトのひな形

```text
あなたはゲームUIのプロ翻訳者です。

目的:
App Update Landingで使用するJSON文言を、指定された言語へ翻訳してください。

原文:
- English（locale: en）を唯一の原文とします。
- 既存の他言語は文脈の参考にできますが、意味が異なる場合はEnglishを優先してください。

対象ファイル:
{{TARGET_FILES}}

対象言語とlocale:
{{TARGET_LOCALES}}

翻訳ルール:
- JSONの文言キー、階層、並び順を変更しないでください。
- English原文を繰り返さず、依頼されたlocaleだけをマージ用JSONとして出力してください。
- 原文にない文言キーや補足フィールドを作らないでください。
- {version}、{start}、{end}、{range}、{days}は綴りも括弧も変更せず、原文にあるものをすべて残してください。
- PurrfectSpirits、App Store、Google Play、バージョン番号は変更しないでください。
- ボタンは短く、状態文と注意書きは自然で明確なゲームUI表現にしてください。
- ArabicとHebrewでは、文言へHTMLタグや方向制御用の不可視文字を追加しないでください。
- 日数の複数形キーは、対象言語で実際に使われる数の条件に合わせて訳してください。
- 説明や注釈を加えず、有効なJSONだけを出力してください。

出力前チェック:
- 対象localeの不足がない
- placeholderの不足、翻訳、重複がない
- JSONとしてparseできる
- English原文の意味、否定、時制、数の条件を維持している

翻訳対象JSON:
{{SOURCE_JSON}}
```

`{{TARGET_FILES}}`、`{{TARGET_LOCALES}}`、`{{SOURCE_JSON}}`は、依頼時の実ファイルに合わせてAIが埋めます。

## 受け入れ後の確認

- JSON Schemaと設定検証を実行する
- すべての必須プレースホルダーが残っていることを確認する
- `locale`の完全一致、言語一致、`en`フォールバックを確認する
- PCとスマートフォンで、ボタンや注意書きのはみ出しがないことを確認する
- ArabicとHebrewでは`html`が`dir="rtl"`となり、タイトル・本文が右から左へ表示されることを確認する
- RTL表示でバージョン、日付範囲、残り日数の並びが崩れていないことを確認する
- 中国語の簡体字と繁体字が正しく切り替わることを確認する
- SNSカードのタイトル、説明、画像altが指定言語で出力されることを確認する
