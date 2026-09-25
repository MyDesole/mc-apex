<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Сброс пароля</title>
</head>
<body style="margin:0; padding:0; background:#0f1115; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color:#e5e7eb;">

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0f1115; padding:32px 16px;">
    <tr>
        <td align="center">

            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px; background:#151922; border:1px solid #232833; border-radius:14px; padding:32px;">

                <tr>
                    <td align="left" style="padding-bottom:24px;">
                            <span style="font-size:18px; font-weight:700; letter-spacing:2px; color:#facc15;">
                                APEX
                            </span>
                    </td>
                </tr>

                <tr>
                    <td align="left" style="padding-bottom:8px;">
                        <h1 style="margin:0; font-size:20px; font-weight:600; color:#f3f4f6;">
                            Сброс пароля
                        </h1>
                    </td>
                </tr>

                <tr>
                    <td align="left" style="padding-bottom:24px;">
                        <p style="margin:0; font-size:14px; line-height:1.6; color:#9ca3af;">
                            Введите этот код на странице восстановления пароля.
                        </p>
                    </td>
                </tr>

                <tr>
                    <td align="center" style="padding: 8px 0 24px;">
                        <div style="display:inline-block; padding:16px 28px; background:#0f1115; border:1px solid #2a2f3a; border-radius:10px;">
                                <span style="font-size:28px; font-weight:700; letter-spacing:8px; color:#f3f4f6; font-family: 'SF Mono', Menlo, Consolas, monospace;">
                                    {{ $code }}
                                </span>
                        </div>
                    </td>
                </tr>

                <tr>
                    <td align="left" style="padding-bottom:24px;">
                        <p style="margin:0; font-size:13px; line-height:1.6; color:#6b7280;">
                            Код действует 15 минут. Если вы не запрашивали сброс — просто проигнорируйте это письмо, пароль останется прежним.
                        </p>
                    </td>
                </tr>

                <tr>
                    <td style="border-top:1px solid #232833; padding-top:16px;">
                        <p style="margin:0; font-size:12px; line-height:1.6; color:#4b5563;">
                            Это автоматическое сообщение, отвечать на него не нужно.
                        </p>
                    </td>
                </tr>

            </table>

        </td>
    </tr>
</table>

</body>
</html>
