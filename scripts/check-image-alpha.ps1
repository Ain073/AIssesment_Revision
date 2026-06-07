param(
    [Parameter(Mandatory = $true)]
    [string] $Path
)

Add-Type -AssemblyName System.Drawing

$imagePath = (Resolve-Path -LiteralPath $Path).ProviderPath
$image = [System.Drawing.Bitmap]::new($imagePath)
$points = @(
    @(0, 0),
    @(($image.Width - 1), 0),
    @(0, ($image.Height - 1)),
    @(($image.Width - 1), ($image.Height - 1)),
    @([Math]::Floor($image.Width / 2), [Math]::Floor($image.Height / 2))
)

foreach ($point in $points) {
    $color = $image.GetPixel($point[0], $point[1])
    Write-Output "$($point[0]),$($point[1]) alpha=$($color.A) rgb=$($color.R),$($color.G),$($color.B)"
}

$image.Dispose()
