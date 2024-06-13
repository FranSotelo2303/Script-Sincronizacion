# Configuración de variables
$folderPath = "C:\Script\subidaTxt"
$serverIP = "ip servidor con direccion"  

# Función para verificar conexión a Internet
function Test-InternetConnection {
    try {
        $webRequest = Invoke-WebRequest -Uri "http://www.google.com" -UseBasicParsing -TimeoutSec 5
        return $true
    } catch {
        return $false
    }
}

# Función para subir archivos al servidor
function Upload-FileToServer {
    param (
        [string]$filePath
    )
    
    try {
        $fileName = [System.IO.Path]::GetFileName($filePath)
        $uri = "$serverIP/$fileName"
        $webRequest = Invoke-WebRequest -Uri $uri -Method Put -InFile $filePath -UseBasicParsing
        if ($webRequest.StatusCode -eq 200) {
            return $true
        } else {
            return $false
        }
    } catch {
        return $false
    }
}

# Función para verificar si un archivo se ha subido correctamente
function Verify-FileUpload {
    param (
        [string]$filePath
    )
    
    $fileName = [System.IO.Path]::GetFileName($filePath)
    $uri = "$serverIP/$fileName"
    
    try {
        $webRequest = Invoke-WebRequest -Uri $uri -UseBasicParsing -Method Head
        if ($webRequest.StatusCode -eq 200) {
            return $true
        } else {
            return $false
        }
    } catch {
        return $false
    }
}

while ($true) {
    # Verificar si la carpeta tiene archivos
    $files = Get-ChildItem -Path $folderPath -File
    if ($files.Count -gt 0) {
        # Verificar conexión a Internet
        if (Test-InternetConnection) {
            foreach ($file in $files) {
                $filePath = $file.FullName
                # Intentar subir el archivo
                if (Upload-FileToServer -filePath $filePath) {
                    # Verificar si el archivo se ha subido correctamente
                    if (Verify-FileUpload -filePath $filePath) {
                        Write-Output "El archivo $filePath se ha subido correctamente."
                    } else {
                        Write-Output "No se pudo verificar la subida del archivo $filePath."
                    }
                } else {
                    Write-Output "No se pudo subir el archivo $filePath."
                }
            }
        } else {
            Write-Output "No hay conexión a Internet."
        }
    } else {
        Write-Output "La carpeta está vacía."
    }
    
    # Esperar 10 minutos antes de volver a verificar
    Start-Sleep -Seconds 600
}