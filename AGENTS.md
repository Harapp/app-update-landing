# AGENTS.md

このプロジェクトで作業するAIエージェントは、作業前にこのファイルと [genie-playbook/AGENTS.md](genie-playbook/AGENTS.md) の必要箇所を確認してください。

## 共通プレイブック

AIエージェントは、プロジェクトルートの `genie-playbook` シンボリックリンクから [genie-playbook/AGENTS.md](genie-playbook/AGENTS.md) を入口として確認してください。
作業内容に合うSkillがある場合は、プロジェクトルートの [skills/INDEX.md](skills/INDEX.md) から該当する `SKILL.md` を確認してください。
`skills` は `genie-playbook/skills` を参照するシンボリックリンクとして配置してください。
`.claude/settings.json` は、必要に応じて `genie-playbook/shared/templates/project/.claude/settings.json` を元に配置してください。

このプロジェクト固有のルールが共通プレイブックと矛盾する場合は、作業前にユーザーへ確認してください。
自動実行や同一Issue内の反復作業では、AGENTS.md類やREADMEの全文再読は必須ではありません。依頼内容、該当Skill、既に与えられたプロンプトから判断できる範囲を優先し、矛盾・不明点・重要判断がある場合に必要箇所を確認してください。

## 作業範囲

- 作業対象: この `AGENTS.md` が属するプロジェクト
- 作業場所: 通常はプロジェクトルート配下。ただし、Git worktree、sandbox、またはAIエージェント/開発ツールが作成した作業用チェックアウトを使う場合は、その作業場所をこのプロジェクトの作業ディレクトリとして扱ってよい
- 作業用チェックアウトを準備する場合は、`genie-playbook` と `skills` もコピーまたはシンボリックリンクで作業場所に用意する
- 編集してよい範囲: 上記の作業場所に含まれるこのプロジェクトのファイル。ただし除外範囲と秘密情報を含む可能性があるファイルは除く
- 触ってはいけない範囲:
  - `genie-playbook/AGENTS.md` で禁止されている範囲
  - `__*` で始まるディレクトリ
  - `_private`
  - `.env`、認証キー、秘密情報を含む可能性があるファイル
  - ユーザーが明示していないGoogle Docs / Sheets / Slides

## 参照順序

これは優先順位であり、毎回すべてを読む必要はありません。
作業内容に関係する範囲を、必要になった時点で確認してください。

1. [AGENTS.md](AGENTS.md)
2. [README.md](README.md)
3. [genie-playbook/AGENTS.md](genie-playbook/AGENTS.md)
4. 作業内容に合うSkillがある場合: [skills/INDEX.md](skills/INDEX.md)
5. コードを作成・編集する場合: [genie-playbook/docs/dev/development-workflow.md](genie-playbook/docs/dev/development-workflow.md)
6. Gitリポジトリで作業する場合: [genie-playbook/docs/dev/git-workflow.md](genie-playbook/docs/dev/git-workflow.md)
7. デザインに関わる作業を行う場合: [genie-playbook/docs/design/design-workflow.md](genie-playbook/docs/design/design-workflow.md)

`genie-playbook/shared/` 配下は大きくなりやすいため、必要な共有資産が明確な場合だけ対象を絞って参照し、広域の `find` や `grep -r` の対象にしないでください。

## 検証

- 変更内容に関係する短時間のテスト、Lint、ビルドは自律的に実行してよい
- 長時間かかる処理、費用が発生する処理、外部サービスへ送信する処理は事前に確認する
- 実行できなかった検証は、理由と未確認内容を報告する

## Git運用

- Gitを使わない場合、この項目は適用しない
- コミット: ユーザーから依頼があった場合のみ実行する
- プッシュ: ユーザーから依頼があった場合のみ実行する
- Gitリポジトリで作業する場合は [genie-playbook/docs/dev/git-workflow.md](genie-playbook/docs/dev/git-workflow.md) を参照する

## AIの進め方

- 依頼内容から判断できる調査・編集・確認は自律的に進めてよい
- 作業範囲、安全性、公開範囲、既存ルールの意味に影響する判断は事前に確認する
- 質問が必要な場合は、作業を止める理由と確認したい点を短くまとめる

## デザイン

- 既存のブランド、トーン、レイアウト、コンポーネント、配色、余白、文体を優先してください。
- UI、Webページ、資料、画像、ブランド表現など、見た目や体験に関わる作業を行う場合は [genie-playbook/docs/design/design-workflow.md](genie-playbook/docs/design/design-workflow.md) を参照してください。
- ブランドの印象を大きく変える変更、公開物の大きなデザイン変更、利用許諾が不明な素材の利用は事前に確認してください。

## プロジェクト固有メモ

- 目的:
- 主な成果物:
- デザインで優先するトーン:
- 参照してよいブランド・デザイン資料:
- 触ってはいけない追加範囲:
- 検証コマンド:

## 作業ログ

- 小さい単発作業では、作業ログは不要です。
- コード変更、複数ファイル変更、未確認事項が残る作業、複数日にまたがる作業では、必要に応じて `logs/ai-work/YYYY-MM-DD.md` に短く記録してください。
- 記録する内容: 変更内容、確認したこと、未確認事項、次回の注意点
- 記録しない内容: 秘密情報、個人情報、コマンド出力全文、差分全文
