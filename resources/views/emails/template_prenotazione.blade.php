<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; color: #333; background: #f5f5f5; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 32px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.1); }
        .header { background: #1a56db; padding: 24px 32px; color: #fff; font-size: 20px; font-weight: bold; }
        .body { padding: 32px; line-height: 1.6; }
        .footer { background: #f5f5f5; padding: 16px 32px; font-size: 12px; color: #888; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">Crono — Gestione Prenotazioni</div>
        <div class="body">
            {!! $corpoRendered !!}
        </div>
        <div class="footer">
            Questa è un'email automatica. Non rispondere a questo messaggio.
        </div>
    </div>
</body>
</html>
