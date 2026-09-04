# App Update Landing Unity Package

App Update Landingの公開APIからイベント状態を取得し、Unity内の表示と詳細ページへの導線を提供するUnityパッケージです。

初期実装には次の機能が含まれます。

- 公開API Schema v2の取得と検証
- `AppUpdateLandingSettings`によるAPI URL設定
- 取得結果のアプリ内共有と`PlayerPrefs`への保存
- イベント状態に応じた再取得抑制と、1日・2日・3日のバックオフ
- 常駐Serviceによるイベント境界・バックオフ期限の自動監視
- `StatusUpdated`による取得結果とローカル状態変化の通知
- 日本語のイベント状態表示（例: `[イベント待ち] 09/05〜10/04 あと1日後に開催`）
- Hostアプリ実装の詳細ダイアログとの連携
- アプリバージョン、ロケール、platform、OSバージョンを付与した詳細ページの表示

## 基本的な使い方

`Window > App Update Landing > Settings`でAPI URLと再取得間隔を設定します。
設定済みの場合、`AppUpdateLandingService`はアプリ起動時に自動生成され、Scene遷移後も
`DontDestroyOnLoad`で生存します。個々のSceneから取得処理を開始する必要はありません。

Serviceは起動直後に通常更新を実行し、その後もイベント開始・終了時刻またはバックオフ期限まで
待機して、必要な時刻だけ状態を更新します。利用側は`StatusUpdated`を購読し、購読前に取得済みの
状態がある場合に備えて`CurrentStatus`も確認してください。

```csharp
using Harapeco.AppUpdateLanding;
using UnityEngine;
using UnityEngine.UI;

public sealed class UpdateStatusView : MonoBehaviour
{
    [SerializeField]
    private Text statusLabel;

    private AppUpdateLandingService service;

    private void Start()
    {
        service = AppUpdateLandingService.Instance;
        if (service == null)
        {
            Debug.LogError("AppUpdateLandingService is unavailable.");
            return;
        }

        service.StatusUpdated += HandleStatusUpdated;

        // 購読前に自動取得が完了していた場合の状態を反映します。
        if (service.CurrentStatus != null)
        {
            HandleStatusUpdated(service.CurrentStatus);
        }
    }

    private void OnDestroy()
    {
        if (service != null)
        {
            service.StatusUpdated -= HandleStatusUpdated;
        }
    }

    private void HandleStatusUpdated(AppUpdateLandingStatus status)
    {
        var display = AppUpdateLandingJapaneseFormatter.Default.Format(status);
        statusLabel.text = display.Text;
    }
}
```

公開された`IsLoaded`フラグはありません。利用可能な状態があるかは
`service.CurrentStatus != null`で確認し、初回取得完了後の状態は`StatusUpdated`で受け取ります。
取得処理を呼び出した箇所で結果や例外を直接受け取りたい場合は、次の明示更新を使用します。

## 明示的な更新

通常はServiceの自動更新だけで動作します。画面表示時やユーザー操作時に現在の状態を明示的に
受け取りたい場合は`RefreshAsync`を使用します。イベント周期、共有キャッシュ、バックオフ条件に
従うため、呼び出しても常にAPI通信が発生するわけではありません。

```csharp
using System.Threading;

var status = await service.RefreshAsync(CancellationToken.None);
```

運用確認などでキャッシュと取得周期を無視してAPIを即時取得する場合だけ、
`ForceRefreshAsync`を使用します。成功した結果は新しい共有キャッシュとして保存され、
次回処理時刻も再計算されます。

```csharp
var status = await service.ForceRefreshAsync(CancellationToken.None);
```

イベントが終了するまでは、通常更新や自動スケジューラから不要なAPI再取得は行いません。

```csharp
// 行全体のタップなどから詳細ページを直接開く場合
service.Client.OpenCurrentPage();

// Hostアプリのダイアログを挟む場合
await service.Client.PresentDetailsAsync(dialogPresenter, CancellationToken.None);
```

テストや一時的な接続先では、常駐スケジューラを使わず従来どおり
`new AppUpdateLandingClient("https://...")`でAPI URLを直接指定できます。

Settings Windowを初めて開くと、次のアセットが作成されます。

```text
Assets/Resources/AppUpdateLandingSettings.asset
```

## Test State

Settingsの`Test State`はSample Scene専用ではありません。Settingsから生成された
`AppUpdateLandingService`または`AppUpdateLandingClient`を利用するすべてのSceneに適用されます。

`Test State`はUnity Editorでのみ有効です。API通信自体は通常どおり実行し、取得後のイベント状態を
`イベント無し`、`イベント前`、`アップデート待ち`、`イベント中`、`イベント後`へ差し替えます。
Player Buildでは設定値を無視し、常にAPIレスポンスを使用します。

Test StateはClient生成時に読み込まれるため、Play Modeへ入る前に設定してください。
API URLを直接指定する`new AppUpdateLandingClient("https://...")`はSettingsを使用しないため、
Test Stateも適用されません。

このリポジトリの開発用Unityプロジェクトでは、動作確認用Sceneを`Assets/Samples/AppUpdateLanding/AppUpdateLandingSample.unity`に用意しています。`イベント前`と`イベント中`では、APIから取得したWebページを開くボタンも表示します。

`IAppUpdateLandingDialogPresenter`はRuntimeパッケージ側でUIを固定しないための境界です。Hostアプリ側で既存のダイアログに接続してください。ダイアログが`OpenPage`を返すと、クライアントが詳細ページを開きます。

再取得間隔はSettingsの`FirstBackoffDays`、`SecondBackoffDays`、`ThirdBackoffDays`、
`MaximumBackoffDays`で設定できます。値が未設定または0以下の場合は、1日・2日・3日・
最大3日の既定値を使用します。取得結果、最終取得時刻、バックオフの進行状況は
`PlayerPrefs`へ保存され、アプリ再起動後も取得サイクルを継続します。
