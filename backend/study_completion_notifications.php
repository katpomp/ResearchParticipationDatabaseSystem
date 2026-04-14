<?php

require_once __DIR__ . '/inc_smtp.php';

/** Credits recorded in attendance / credits tables when a study is marked complete. */
function sona_study_completion_credit_amount(): float
{
    return 3.0;
}

function sona_send_study_completion_email(string $toEmail, string $firstName, string $studyTitle, float $creditsEarned): bool
{
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $name = $firstName !== '' ? $firstName : 'Student';
    $credStr = rtrim(rtrim(number_format($creditsEarned, 2, '.', ''), '0'), '.');

    $hName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $hTitle = htmlspecialchars($studyTitle, ENT_QUOTES, 'UTF-8');
    $hCred = htmlspecialchars($credStr, ENT_QUOTES, 'UTF-8');

    $plain = "Hello {$name},\r\n\r\n"
        . "Your participation has been confirmed. You successfully completed this study:\r\n\r\n"
        . "Study: {$studyTitle}\r\n"
        . "Credits awarded: {$credStr}\r\n\r\n"
        . "These credits are on your Research Participation record. Open My Schedule/Credits in the portal to see your total.\r\n\r\n"
        . "If this message was sent in error, contact your researcher or course support.\r\n\r\n"
        . "—\r\n"
        . "Automated message. Please do not reply to this email.\r\n";

    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Study completed</title>
</head>
<body style="margin:0;padding:0;background:#eef1f5;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef1f5;border-collapse:collapse;">
<tr>
<td style="padding:20px 12px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;border-collapse:separate;border:1px solid #dde3ea;box-shadow:0 4px 14px rgba(0,0,0,0.06);">
<tr>
<td style="background:#003366;padding:22px 24px;">
<p style="margin:0;font-family:Georgia,'Times New Roman',serif;font-size:19px;font-weight:700;color:#ffffff;line-height:1.3;">Research participation</p>
<p style="margin:6px 0 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:13px;color:#a8bdd4;letter-spacing:0.02em;">Christopher Newport University</p>
</td>
</tr>
<tr>
<td style="padding:8px 24px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
<p style="margin:20px 0 0;font-size:15px;line-height:1.55;color:#334155;">Hello {$hName},</p>
<p style="margin:14px 0 0;font-size:15px;line-height:1.55;color:#334155;">Your participation is confirmed. You successfully completed this study:</p>
</td>
</tr>
<tr>
<td style="padding:18px 24px 8px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f7fb;border-radius:10px;border:1px solid #dce4ee;border-collapse:separate;">
<tr>
<td style="padding:18px 20px;">
<p style="margin:0 0 6px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#64748b;">Study</p>
<p style="margin:0 0 18px;font-size:16px;font-weight:600;line-height:1.45;color:#0f172a;">{$hTitle}</p>
<p style="margin:0 0 6px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#64748b;">Credits awarded</p>
<p style="margin:0;font-size:26px;font-weight:700;line-height:1.2;color:#003366;">{$hCred}</p>
</td>
</tr>
</table>
</td>
</tr>
<tr>
<td style="padding:4px 24px 22px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
<p style="margin:0;font-size:15px;line-height:1.55;color:#475569;">These credits are on your Research Participation record. Open <strong style="color:#1e293b;">My Schedule/Credits</strong> in the portal to review your total.</p>
<p style="margin:16px 0 0;font-size:14px;line-height:1.5;color:#64748b;">If you believe this was sent in error, contact your researcher or course support.</p>
</td>
</tr>
<tr>
<td style="padding:16px 24px;background:#f8fafc;border-top:1px solid #e8edf3;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:12px;line-height:1.5;color:#7c8a9a;">
Automated message — please do not reply to this email.
</td>
</tr>
</table>
</td>
</tr>
</table>
</body>
</html>
HTML;

    $subject = 'CNU Research Participation — Study completed';
    return sona_send_html_email($toEmail, $subject, $html, $plain);
}
