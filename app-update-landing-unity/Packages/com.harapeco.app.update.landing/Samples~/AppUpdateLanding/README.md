# App Update Landing Sample

`AppUpdateLandingSample.unity`をPlayすると、SettingsのURLから公開APIを取得し、イベント状態を表示します。

UIはScene内に保存された`Canvas`、`Image`、`Text`、`Button`で構成され、実行時には生成しません。`Canvas Scaler`の基準解像度は`640 x 640`、Width/HeightのMatchは`0.5`です。各要素をアンカーで配置しているため、縦長・横長のGame Viewでも使用できます。

## 確認手順

1. Package ManagerからImportした場合は、`App Update Landing Sample`フォルダを開く
2. `Window > App Update Landing > Settings`を開く
3. `API Url`を設定する
4. `Test State`で確認したい状態を選ぶ
5. `AppUpdateLandingSample.unity`を開いてPlayする

`API Response`ではAPIから返された状態をそのまま表示します。その他の項目でもAPI取得自体は実行し、取得したURLやバージョンを保ったまま、Unity Editor内の表示状態だけを差し替えます。

- `イベント無し`: イベント無しの表示
- `イベント前`: 開催前の表示とWebページボタン
- `アップデート待ち`: イベント期間中でアプリのリリース待ちの表示
- `イベント中`: 開催中の表示とWebページボタン
- `イベント後`: 終了後の表示

テスト状態の差し替えはUnity Editor内だけで有効です。Player Buildでは常にAPIレスポンスを使用します。
