# Discord Webhook Tags Reference

## Available Tags by Event Type

### Warning Added Event (`add_event.json`)

- `{id}` - The ID of the warning that was issued
- `{player}` - The name of the player who received the warning
- `{source}` - The moderator/source that issued the warning
- `{reason}` - The reason for the warning
- `{timestamp}` - The date and time the warning was issued
- `{expiration}` - How long the warning will last before expiring
- `{count}` - The total number of active warnings for the player

### Warning Removed Event (`remove_event.json`)

- `{id}` - The ID of the warning that was removed
- `{player}` - The name of the player whose warning was removed
- `{reason}` - The reason of the removed warning
- `{source}` - The moderator/source that originally issued the warning
- `{timestamp}` - The date and time the warning was originally issued
- `{remainingCount}` - The number of warnings remaining for the player

### Warning Edited Event (`edit_event.json`)

- `{id}` - The ID of the warning that was edited
- `{player}` - The name of the player whose warning was edited
- `{reason}` - The current reason of the warning
- `{source}` - The moderator/source that originally issued the warning
- `{editType}` - The type of edit (e.g., "reason", "expiration")
- `{oldValue}` - The previous value before the edit
- `{newValue}` - The new value after the edit

### Warning Expired Event (`expire_event.json`)

- `{id}` - The ID of the warning that expired
- `{player}` - The name of the player whose warning expired
- `{reason}` - The reason of the expired warning
- `{source}` - The moderator/source that originally issued the warning
- `{expirationDate}` - The date and time when the warning expired
- `{remainingCount}` - The number of warnings remaining for the player

### Player Punishment Event (`punishment_event.json`)

- `{player}` - The name of the player being punished
- `{punishmentType}` - The type of punishment (kick, ban, ban-ip, tempban, none)
- `{issuerName}` - The name of the moderator issuing the punishment
- `{reason}` - The reason for the punishment
- `{warningCount}` - The total number of warnings the player had when punished

## Example Template Customization

You can use these tags in your webhook templates to create more detailed messages. For example:

```json
{
  "embeds": [
    {
      "title": "Warning Issued",
      "fields": [
        {
          "name": "Warning ID",
          "value": "{id}",
          "inline": true
        },
        {
          "name": "Player",
          "value": "{player}",
          "inline": true
        },
        {
          "name": "Total Warnings",
          "value": "{count}",
          "inline": true
        },
        {
          "name": "Issued By",
          "value": "{source}",
          "inline": true
        },
        {
          "name": "Reason",
          "value": "{reason}",
          "inline": false
        },
        {
          "name": "Issued At",
          "value": "{timestamp}",
          "inline": true
        },
        {
          "name": "Expires",
          "value": "{expiration}",
          "inline": true
        }
      ]
    }
  ]
}
```
