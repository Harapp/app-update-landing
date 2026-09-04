# App Update Landing Unity Package

App Update Landingの公開APIからイベント状態を取得し、Unity内の表示と詳細ページへの導線を提供するUnityパッケージです。

初期実装には次の機能が含まれます。

- 公開API Schema v2の取得と検証
- `AppUpdateLandingSettings`によるAPI URL設定
- `StatusUpdated`による取得結果の通知
- 日本語のイベント状態表示（例: `[イベント待ち] 09/05〜10/04 あと1日後に開催`）
- Hostアプリ実装の詳細ダイアログとの連携
- アプリバージョン、ロケール、platform、OSバージョンを付与した詳細ページの表示

```csharp
using System.Threading;
using Harapeco.AppUpdateLanding;

// Window > App Update Landing > SettingsでAPI URLを設定しておきます。
var client = new AppUpdateLandingClient();

client.StatusUpdated += status =>
{
    var display = AppUpdateLandingJapaneseFormatter.Default.Format(status);
    statusLabel.text = display.Text;
};

await client.RefreshAsync(CancellationToken.None);

// 行全体のタップなどから詳細ページを直接開く場合
client.OpenCurrentPage();

// Hostアプリのダイアログを挟む場合
await client.PresentDetailsAsync(dialogPresenter, CancellationToken.None);
```

Settings Windowを初めて開くと、次のアセットが作成されます。

```text
Assets/Resources/AppUpdateLandingSettings.asset
```

テストや一時的な接続先では、従来どおり`new AppUpdateLandingClient("https://...")`でAPI URLを直接指定できます。

`IAppUpdateLandingDialogPresenter`はRuntimeパッケージ側でUIを固定しないための境界です。Hostアプリ側で既存のダイアログに接続してください。ダイアログが`OpenPage`を返すと、クライアントが詳細ページを開きます。
