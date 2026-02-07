<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #f4f4f4; padding: 20px; border-radius: 5px; }
        .content { padding: 20px 0; }
        .footer { font-size: 12px; color: #666; margin-top: 30px; }
        .alert { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Atualização sobre Incidente de Sistema</h2>
        </div>
        
        <div class="content">
            <p>Olá {{ $user->name }},</p>
            
            <p>Gostaríamos de informá-lo(a) sobre um breve incidente técnico que afetou nosso sistema:</p>
            
            <div class="alert">
                <strong>🔧 Incidente {{ $incident['incident_id'] }}</strong><br>
                <strong>Período:</strong> {{ $incident['detected_at'] }} - {{ $incident['resolved_at'] }}<br>
                <strong>Duração:</strong> {{ $incident['duration'] }}<br>
                <strong>Status:</strong> {{ $incident['status'] }}
            </div>
            
            <h3>O que aconteceu?</h3>
            <p>
                Durante o período mencionado, nosso sistema enfrentou uma indisponibilidade parcial 
                que pode ter afetado sua experiência. Identificamos que você estava usando nossos 
                serviços durante esse período.
            </p>
            
            <h3>Qual foi o impacto para você?</h3>
            <ul>
                @foreach($incident['impacts'] ?? [] as $impact)
                    <li>{{ $impact }}</li>
                @endforeach
            </ul>
            
            <h3>O problema foi resolvido?</h3>
            <p>
                Sim! O incidente foi totalmente resolvido em {{ $incident['resolved_at'] }}. 
                Implementamos medidas preventivas para evitar ocorrências similares:
            </p>
            <ul>
                @foreach($incident['preventive_measures'] ?? [] as $measure)
                    <li>✅ {{ $measure }}</li>
                @endforeach
            </ul>
            
            <h3>Compensação</h3>
            <p>
                Como forma de compensação pelo inconveniente, estamos oferecendo:
            </p>
            <ul>
                <li>{{ $compensation_type }}</li>
                <li>Suporte prioritário pelos próximos 30 dias</li>
            </ul>
            
            <p>
                Se você tiver dúvidas ou precisar de assistência, nossa equipe está à disposição 
                através do email <a href="mailto:{{ $support_email ?? 'support@refresher.com.br' }}">{{ $support_email ?? 'support@refresher.com.br' }}</a>.
            </p>
            
            <p>Agradecemos sua compreensão e confiança.</p>
            
            <p>
                Atenciosamente,<br>
                <strong>{{ $company_name ?? 'Equipe Refresher Trends' }}</strong>
            </p>
        </div>
        
        <div class="footer">
            <p>
                Este email é referente ao incidente {{ $incident['incident_id'] }}.<br>
                Você está recebendo porque acessou nossos serviços durante o período do incidente.
            </p>
        </div>
    </div>
</body>
</html>
