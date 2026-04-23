$body = @{
    tracking_id = "test123456789test123456789test1234567890"
    offre_id = 1
    score = 75
    evaluation = "Offre de bonne qualité"
    points_faibles = @("Point 1", "Point 2")
    ameliorations = @("Amélioration 1", "Amélioration 2")
    offre_optimisee = @{
        titre = "Titre optimisé"
        description = "Description optimisée"
        pourcentage_suggere = 15
    }
    diffusion = @{
        canaux = @("Email", "Social")
        timing = "2 semaines"
        public_cible = "Couples"
    }
} | ConvertTo-Json -Depth 10

Write-Host "Sending webhook test with tracking_id: test123456789test123456789test1234567890"
Write-Host "Body: $body"

$response = Invoke-WebRequest -Uri "http://localhost:8000/webhook/offres/analyze-callback" `
  -Method Post `
  -ContentType "application/json" `
  -Body $body

Write-Host "Response Status: $($response.StatusCode)"
Write-Host "Response Content: $($response.Content)"
