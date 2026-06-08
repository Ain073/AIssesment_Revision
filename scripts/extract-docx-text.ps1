param(
    [Parameter(Mandatory = $true)]
    [string] $InputPath,

    [Parameter(Mandatory = $true)]
    [string] $OutputPath
)

Add-Type -AssemblyName System.IO.Compression.FileSystem

$inputFullPath = (Resolve-Path -LiteralPath $InputPath).ProviderPath
$outputFullPath = [System.IO.Path]::GetFullPath($OutputPath)
$outputDirectory = [System.IO.Path]::GetDirectoryName($outputFullPath)

if (-not [System.IO.Directory]::Exists($outputDirectory)) {
    [System.IO.Directory]::CreateDirectory($outputDirectory) | Out-Null
}

$zip = [System.IO.Compression.ZipFile]::OpenRead($inputFullPath)

try {
    $entries = $zip.Entries |
        Where-Object {
            $_.FullName -eq "word/document.xml" -or
            $_.FullName -like "word/header*.xml" -or
            $_.FullName -like "word/footer*.xml"
        } |
        Sort-Object FullName

    $paragraphs = New-Object System.Collections.Generic.List[string]

    foreach ($entry in $entries) {
        $reader = New-Object System.IO.StreamReader($entry.Open())
        try {
            [xml] $xml = $reader.ReadToEnd()
        }
        finally {
            $reader.Dispose()
        }

        $namespace = New-Object System.Xml.XmlNamespaceManager($xml.NameTable)
        $namespace.AddNamespace("w", "http://schemas.openxmlformats.org/wordprocessingml/2006/main")

        $nodes = $xml.SelectNodes("//w:p", $namespace)
        foreach ($node in $nodes) {
            $textNodes = $node.SelectNodes(".//w:t", $namespace)
            $parts = foreach ($textNode in $textNodes) { $textNode.InnerText }
            $line = ($parts -join "")

            if (-not [string]::IsNullOrWhiteSpace($line)) {
                $paragraphs.Add($line.Trim()) | Out-Null
            }
        }
    }

    [System.IO.File]::WriteAllLines($outputFullPath, $paragraphs)
    Write-Output $outputFullPath
}
finally {
    $zip.Dispose()
}
