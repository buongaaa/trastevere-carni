# Rende trasparente lo sfondo nero delle foto della carne.
# Metti le foto originali (sfondo nero) in img\originali\ con i nomi:
#   tbone.png salsicce.png prosciutto.png salame.png costata.png guanciale.png
# poi esegui:  powershell -ExecutionPolicy Bypass -File pulisci-sfondo.ps1
Add-Type -AssemblyName System.Drawing
Add-Type -ReferencedAssemblies System.Drawing -TypeDefinition @"
using System; using System.Drawing; using System.Drawing.Imaging; using System.Runtime.InteropServices;
public static class Cutout {
  public static void Run(string src, string dst, int lo, int hi) {
    using (var input = new Bitmap(src))
    using (var bmp = new Bitmap(input.Width, input.Height, PixelFormat.Format32bppArgb)) {
      using (var g = Graphics.FromImage(bmp)) g.DrawImage(input, 0, 0, input.Width, input.Height);
      var r = new Rectangle(0, 0, bmp.Width, bmp.Height);
      var d = bmp.LockBits(r, ImageLockMode.ReadWrite, PixelFormat.Format32bppArgb);
      var px = new byte[d.Stride * d.Height];
      Marshal.Copy(d.Scan0, px, 0, px.Length);
      for (int i = 0; i < px.Length; i += 4) {
        int m = Math.Max(px[i], Math.Max(px[i + 1], px[i + 2]));
        int a = m <= lo ? 0 : m >= hi ? 255 : (m - lo) * 255 / (hi - lo);
        px[i + 3] = (byte)a;
      }
      Marshal.Copy(px, 0, d.Scan0, px.Length);
      bmp.UnlockBits(d);
      bmp.Save(dst, ImageFormat.Png);
    }
  }
}
"@
$root = Split-Path -Parent $MyInvocation.MyCommand.Path
Get-ChildItem "$root\img\originali\*.png" | ForEach-Object {
  [Cutout]::Run($_.FullName, "$root\img\$($_.Name)", 14, 48)
  "OK  $($_.Name)"
}
