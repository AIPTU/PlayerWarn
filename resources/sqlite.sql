-- #! sqlite
-- #{ table
-- #  { init
CREATE TABLE IF NOT EXISTS player_warnings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    player_name VARCHAR(32) NOT NULL COLLATE NOCASE,
    reason TEXT NOT NULL,
    source VARCHAR(32) NOT NULL,
    expiration DATETIME DEFAULT NULL,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_player_name ON player_warnings(player_name);
-- #  }
-- #}
-- #{ warn
-- #  { add
-- #    :player_name string
-- #    :reason string
-- #    :source string
-- #    :expiration ?string
-- #    :timestamp string
INSERT INTO player_warnings (
        player_name,
        reason,
        source,
        expiration,
        timestamp
    )
VALUES (
        :player_name,
        :reason,
        :source,
        :expiration,
        :timestamp
    );
-- #  }
-- #  { remove_id
-- #    :id int
-- #    :player_name string
DELETE FROM player_warnings
WHERE id = :id
    AND player_name = :player_name;
-- #  }
-- #  { remove_player
-- #    :player_name string
DELETE FROM player_warnings
WHERE player_name = :player_name;
-- #  }
-- #  { get_id
-- #    :id int
-- #    :player_name string
SELECT *
FROM player_warnings
WHERE id = :id
    AND player_name = :player_name;
-- #  }
-- #  { get_all
-- #    :player_name string
SELECT *
FROM player_warnings
WHERE player_name = :player_name
ORDER BY timestamp DESC;
-- #  }
-- #  { count
-- #    :player_name string
SELECT COUNT(*) as count
FROM player_warnings
WHERE player_name = :player_name
    AND (
        expiration IS NULL
        OR expiration > CURRENT_TIMESTAMP
    );
-- #  }
-- #  { get_expired
SELECT *
FROM player_warnings
WHERE expiration IS NOT NULL
    AND expiration <= CURRENT_TIMESTAMP;
-- #  }
-- #  { delete_expired
DELETE FROM player_warnings
WHERE expiration IS NOT NULL
    AND expiration <= CURRENT_TIMESTAMP;
-- #  }
-- #  { update_reason
-- #    :id int
-- #    :player_name string
-- #    :reason string
UPDATE player_warnings
SET reason = :reason
WHERE id = :id
    AND player_name = :player_name;
-- #  }
-- #  { update_expiration
-- #    :id int
-- #    :player_name string
-- #    :expiration ?string
UPDATE player_warnings
SET expiration = :expiration
WHERE id = :id
    AND player_name = :player_name;
-- #  }
-- #}