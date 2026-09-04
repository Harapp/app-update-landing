# App Update Landing Unity Package

App Update Landingの公開APIからイベント状態を取得し、Unity内の表示と詳細ページへの導線を提供するUnityパッケージです。

初期実装には次の機能が含まれます。

- 公開API Schema v2の取得と検証
- `AppUpdateLandingSettings`によるAPI URL設定
- 取得結果のアプリ内共有と`PlayerPrefs`への保存
- イベント状態に応じた再取得抑制と、1日・2日・3日のバックオフ
- `StatusUpdated`による取得結果の通知
- 日本語のイベント状態表示（例: `[イベント待ち] 09/05〜10/04 あと1日後に開催`）
- Hostアプリ実装の詳細ダイアログとの連携
- アプリバージョン、ロケール、platform、OSバージョンを付与した詳細ページの表示

```csharp
using System.Threading;
using Harapeco.AppUpdateLanding;

// Window > App Update Landing > SettingsでAPI URLと再取得間隔を設定しておきます。
var client = new AppUpdateLandingClient();

client.StatusUpdated += status =>
{
    var display = AppUpdateLandingJapaneseFormatter.Default.Format(status);
    statusLabel.text = display.Text;
};

await client.RefreshAsync(CancellationToken.None);

// 同じAPI URLとplatformを使うClientは取得結果と取得サイクルを共有します。
// イベントが終了するまでは、時刻に応じた状態更新だけが行われ、APIを再取得しません。

// 行全体のタップなどから詳細ページを直接開く場合
client.OpenCurrentPage();

// Hostアプリのダイアログを挟む場合
await client.PresentDetailsAsync(dialogPresenter, CancellationToken.None);
```

Settings Windowを初めて開くと、次のアセットが作成されます。

```text
Assets/Resources/AppUpdateLandingSettings.asset
```

Unity Editorでは、Settingsの`Test State`でAPI取得後の表示を`イベント無し`、`イベント前`、`アップデート待ち`、`イベント中`、`イベント後`へ差し替えられます。Player Buildではこの設定を無視し、常にAPIレスポンスを使用します。

このリポジトリの開発用Unityプロジェクトでは、動作確認用Sceneを`Assets/Samples/AppUpdateLanding/AppUpdateLandingSample.unity`に用意しています。`イベント前`と`イベント中`では、APIから取得したWebページを開くボタンも表示します。

テストや一時的な接続先では、従来どおり`new AppUpdateLandingClient("https://...")`でAPI URLを直接指定できます。

`IAppUpdateLandingDialogPresenter`はRuntimeパッケージ側でUIを固定しないための境界です。Hostアプリ側で既存のダイアログに接続してください。ダイアログが`OpenPage`を返すと、クライアントが詳細ページを開きます。

再取得間隔はSettingsの`FirstBackoffDays`、`SecondBackoffDays`、`ThirdBackoffDays`、
`MaximumBackoffDays`で設定できます。値が未設定または0以下の場合は、1日・2日・3日・
最大3日の既定値を使用します。取得結果、最終取得時刻、バックオフの進行状況は
`PlayerPrefs`へ保存され、アプリ再起動後も取得サイクルを継続します。
