param([string]$BaseUrl = 'http://localhost:8080')
$ErrorActionPreference = 'Stop'
$noteId = $null
function Assert-True($condition, $message) { if (-not $condition) { throw $message } }
try {
    $health = Invoke-WebRequest "$BaseUrl/up"
    Assert-True ($health.StatusCode -eq 200) 'Health check failed'
    $ui = Invoke-WebRequest $BaseUrl
    Assert-True ($ui.Content -match 'id="app"') 'Vue entrypoint missing'
    $docs = Invoke-RestMethod "$BaseUrl/docs/openapi.json"
    Assert-True ($docs.openapi -eq '3.0.3') 'OpenAPI missing'
    $payload = @{title='Проверка 100%';content='Тестовая заметка'} | ConvertTo-Json
    $created = Invoke-WebRequest "$BaseUrl/api/notes" -Method Post -ContentType 'application/json; charset=utf-8' -Body ([Text.Encoding]::UTF8.GetBytes($payload))
    Assert-True ($created.StatusCode -eq 201) 'Create should return 201'
    $noteId = ($created.Content | ConvertFrom-Json).data.id
    $shown = Invoke-RestMethod "$BaseUrl/api/notes/$noteId"
    Assert-True ($shown.data.title -eq 'Проверка 100%') 'Unicode roundtrip failed'
    $patch = @{title='Изменённая заметка'} | ConvertTo-Json
    $updated = Invoke-RestMethod "$BaseUrl/api/notes/$noteId" -Method Patch -ContentType 'application/json; charset=utf-8' -Body ([Text.Encoding]::UTF8.GetBytes($patch))
    Assert-True ($updated.data.content -eq 'Тестовая заметка') 'PATCH lost content'
    $list = Invoke-RestMethod "$BaseUrl/api/notes?per_page=10"
    Assert-True ($list.data.id -contains $noteId) 'List does not contain created note'
    $deleted = Invoke-WebRequest "$BaseUrl/api/notes/$noteId" -Method Delete
    Assert-True ($deleted.StatusCode -eq 204) 'Delete should return 204'
    $noteId = $null
    Write-Output 'PASS: health, UI entrypoint, OpenAPI, Unicode CRUD, list, PATCH, DELETE'
} finally {
    if ($noteId) { Invoke-RestMethod "$BaseUrl/api/notes/$noteId" -Method Delete | Out-Null }
}
