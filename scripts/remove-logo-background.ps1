param(
    [Parameter(Mandatory = $true)]
    [string] $InputPath,

    [Parameter(Mandatory = $true)]
    [string] $OutputPath,

    [int] $WhiteThreshold = 238
)

Add-Type -AssemblyName System.Drawing

$sourcePath = (Resolve-Path -LiteralPath $InputPath).ProviderPath
$outputFullPath = [System.IO.Path]::GetFullPath($OutputPath)
$outputDirectory = [System.IO.Path]::GetDirectoryName($outputFullPath)

if (-not [System.IO.Directory]::Exists($outputDirectory)) {
    [System.IO.Directory]::CreateDirectory($outputDirectory) | Out-Null
}

$source = [System.Drawing.Bitmap]::new($sourcePath)
$bitmap = [System.Drawing.Bitmap]::new($source.Width, $source.Height, [System.Drawing.Imaging.PixelFormat]::Format32bppArgb)
$graphics = [System.Drawing.Graphics]::FromImage($bitmap)
$graphics.DrawImage($source, 0, 0, $source.Width, $source.Height)
$graphics.Dispose()
$source.Dispose()

$width = $bitmap.Width
$height = $bitmap.Height
$visited = [bool[]]::new($width * $height)
$queue = [System.Collections.Generic.Queue[int]]::new()

function Test-IsOuterWhite {
    param([System.Drawing.Color] $Color)

    return $Color.R -ge $WhiteThreshold -and $Color.G -ge $WhiteThreshold -and $Color.B -ge $WhiteThreshold
}

function Add-Pixel {
    param([int] $X, [int] $Y)

    if ($X -lt 0 -or $Y -lt 0 -or $X -ge $width -or $Y -ge $height) {
        return
    }

    $index = ($Y * $width) + $X
    if ($visited[$index]) {
        return
    }

    $visited[$index] = $true
    $color = $bitmap.GetPixel($X, $Y)

    if (Test-IsOuterWhite -Color $color) {
        $queue.Enqueue($index)
    }
}

for ($x = 0; $x -lt $width; $x++) {
    Add-Pixel -X $x -Y 0
    Add-Pixel -X $x -Y ($height - 1)
}

for ($y = 0; $y -lt $height; $y++) {
    Add-Pixel -X 0 -Y $y
    Add-Pixel -X ($width - 1) -Y $y
}

while ($queue.Count -gt 0) {
    $index = $queue.Dequeue()
    $x = $index % $width
    $y = [Math]::Floor($index / $width)
    $original = $bitmap.GetPixel($x, $y)
    $bitmap.SetPixel($x, $y, [System.Drawing.Color]::FromArgb(0, $original.R, $original.G, $original.B))

    Add-Pixel -X ($x + 1) -Y $y
    Add-Pixel -X ($x - 1) -Y $y
    Add-Pixel -X $x -Y ($y + 1)
    Add-Pixel -X $x -Y ($y - 1)
}

$bitmap.Save($outputFullPath, [System.Drawing.Imaging.ImageFormat]::Png)
$bitmap.Dispose()

Write-Output $outputFullPath
