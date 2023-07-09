# PlayerWarn

A PocketMine-MP plugin that allows server administrators to issue warnings to players and keep track of their warning history.

# Features
- Warn players for rule violations or misconduct.
- Set a warning limit per player and define different punishment types when the limit is reached (kick, ban, or ban-ip).
- View and clear warnings for players.
- Customization options through the configuration file.

# Default Config
``` yaml
# PlayerWarn Configuration

# The `warning_limit` option specifies the maximum number of warnings a player can have before being banned.
# Set a positive integer value for this option. The default value is 3.
warning_limit: 3

# The `punishment_type` option specifies the type of punishment to apply when a player reaches the warning limit.
# Valid options are "kick", "ban", "ban-ip", and "none".
# The default value is "none", which means no punishment will be applied.
punishment_type: none
```

# Commands
The PlayerWarn plugin provides the following commands for chunk clearing:

- `/warn <player> [reason]`: Command to issue a warning to a player. Specify the player's username and an optional reason for the warning.
  - Permission: `playerwarn.command.warn`
- `/clearwarns <player>`: Command to clear all warnings for a player.
  - Permission: `playerwarn.command.warn`
- `/warns [player]`: Command to view all warnings. If a player is specified, it will show the warnings for that player. If no player is specified, it will show warnings for all players.
  - Permission: `playerwarn.command.warn`

# Permissions
To control access to the commands provided by the PlayerWarn plugin, the following permissions are available:

- `playerwarn.command.warn`: Allows players to use the `/warn` command.
- `playerwarn.command.clearwarns`: Allows players to use the `/clearwarns` command.
- `playerwarn.command.warns`: Allows players to use the `/warns` command.

Grant these permissions to specific player groups or individuals using a permissions management plugin of your choice.

# Upcoming Features

- Currently none planned. You can contribute or suggest for new features.

# Additional Notes

- If you find bugs or want to give suggestions, please visit [here](https://github.com/AIPTU/PlayerWarn/issues).
- We accept all contributions! If you want to contribute, please make a pull request in [here](https://github.com/AIPTU/PlayerWarn/pulls).
- Icons made from [www.flaticon.com](https://www.flaticon.com)
