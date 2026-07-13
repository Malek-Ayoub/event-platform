<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Your ticket</title>
</head>
<body style="font-family: sans-serif; color: #111827; line-height: 1.5;">
    <h1 style="font-size: 20px; margin-bottom: 8px;">{{ data_get($snapshot, 'event.name') }}</h1>
    <p style="color: #4b5563; margin-top: 0;">
        {{ data_get($snapshot, 'venue.name') }}
        @if (data_get($snapshot, 'event.starts_at'))
            · {{ data_get($snapshot, 'event.starts_at') }}
        @endif
    </p>

    <table style="border-collapse: collapse; margin: 16px 0;">
        <tr>
            <td style="padding: 4px 16px 4px 0; color: #6b7280;">Ticket</td>
            <td style="padding: 4px 0; font-weight: bold;">{{ data_get($snapshot, 'ticket.number') }}</td>
        </tr>
        <tr>
            <td style="padding: 4px 16px 4px 0; color: #6b7280;">Holder</td>
            <td style="padding: 4px 0; font-weight: bold;">{{ data_get($snapshot, 'holder.name') }}</td>
        </tr>
        <tr>
            <td style="padding: 4px 16px 4px 0; color: #6b7280;">Type</td>
            <td style="padding: 4px 0; font-weight: bold;">{{ data_get($snapshot, 'ticket_type.name') }}</td>
        </tr>
        @if (data_get($snapshot, 'seat.label'))
            <tr>
                <td style="padding: 4px 16px 4px 0; color: #6b7280;">Seat</td>
                <td style="padding: 4px 0; font-weight: bold;">{{ data_get($snapshot, 'seat.label') }}</td>
            </tr>
        @endif
    </table>

    <p style="margin-top: 24px;">Your ticket PDF is attached to this email.</p>
</body>
</html>
