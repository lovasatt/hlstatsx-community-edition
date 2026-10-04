using CounterStrikeSharp.API;
using CounterStrikeSharp.API.Core;
using CounterStrikeSharp.API.Core.Attributes.Registration;
using CounterStrikeSharp.API.Modules.Commands;
using CounterStrikeSharp.API.Modules.Menu;
using CounterStrikeSharp.API.Modules.Utils;
using CounterStrikeSharp.API.Modules.Timers;
using CounterStrikeSharp.API.Modules.Entities;
using MySqlConnector;
using System.Text.Json.Serialization;
using System.Collections.Concurrent;
using System.Linq;
using System.Text.RegularExpressions;
using System.Net;
using System.Net.Mail;

namespace HLStatsX_CS2;

public class HLXConfig : BasePluginConfig
{
    [JsonPropertyName("DatabaseHost")] public string DatabaseHost { get; set; } = "127.0.0.1";
    [JsonPropertyName("DatabasePort")] public int DatabasePort { get; set; } = 3306;
    [JsonPropertyName("DatabaseUser")] public string DatabaseUser { get; set; } = "hlxuser";
    [JsonPropertyName("DatabasePassword")] public string DatabasePassword { get; set; } = "password";
    [JsonPropertyName("DatabaseName")] public string DatabaseName { get; set; } = "hlstatsx";
    [JsonPropertyName("CooldownSeconds")] public int CooldownSeconds { get; set; } = 30;
}

public class HLXSession
{
    public uint PlayerId { get; set; }
    public int Skill { get; set; }
    public int LastSkillChange { get; set; }
    public int RankPos { get; set; }
    public string RankName { get; set; } = "";
    public string RealName { get; set; } = "";
    public string Homepage { get; set; } = "";
    public int IsHidden { get; set; }
    public int Kills { get; set; }
    public int Headshots { get; set; }
    public int Deaths { get; set; }
    public double Accuracy { get; set; }
    public DateTime LastActivity { get; set; }
    public bool AutoReport { get; set; } = false;
}

public class HLStatsX_CS2 : BasePlugin, IPluginConfig<HLXConfig>
{
    public override string ModuleName => "HLStatsX Modern Interface";
    public override string ModuleVersion => "1.3";
    public override string ModuleAuthor => "lovasatt";
    public override string ModuleDescription => "Integrates HLStatsX with CS2 for viewing and updating player stats via in-game menu.";

    public HLXConfig Config { get; set; } = new();
    private readonly ConcurrentDictionary<int, HLXSession> _activeSessions = new();
    private readonly ConcurrentDictionary<ulong, (string Field, DateTime Time)> _waitingForInput = new();
    private readonly ConcurrentDictionary<ulong, DateTime> _lastInputUsage = new();
    private bool _isCenterMessageActive = false;
    private string _activeCenterHtml = string.Empty;
    private DateTime _centerMessageEndTime = DateTime.MinValue;

    public void OnConfigParsed(HLXConfig config) => Config = config;

    public override void Load(bool hotReload)
    {
        RegisterListener<Listeners.OnTick>(OnTick);

        AddTimer(1.0f, () =>
        {
            var now = DateTime.Now;
            foreach (var slot in _activeSessions.Keys.ToList())
            {
                if (_activeSessions.TryGetValue(slot, out var session))
                {
                    var p = Utilities.GetPlayerFromSlot(slot);
                    if ((now - session.LastActivity).TotalSeconds > 30)
                    {
                        if (p != null)
                        {
                            _waitingForInput.TryRemove(p.SteamID, out _);
                            _lastInputUsage.TryRemove(p.SteamID, out _);
                            if (p.IsValid)
                                MenuManager.CloseActiveMenu(p);
                        }
                        _activeSessions.TryRemove(slot, out _);
                    }
                }
            }

            foreach (var input in _waitingForInput.ToList())
            {
                if ((now - input.Value.Time).TotalSeconds > 30)
                {
                    _waitingForInput.TryRemove(input.Key, out _);
                    _lastInputUsage.TryRemove(input.Key, out _);

                    foreach (var p in Utilities.GetPlayers())
                    {
                        if (p.SteamID == input.Key)
                        {
                            if (p.IsValid)
                            {
                                p.PrintToChat(Localizer["status.timeout"]);
                            }
                            break;
                        }
                    }
                }
            }

        }, TimerFlags.REPEAT);

        AddCommandListener("say", OnPlayerSay);
        AddCommandListener("say_team", OnPlayerSay);

        RegisterEventHandler<EventRoundStart>((@e, @i) =>
        {
            return HookResult.Continue;
        });

        RegisterEventHandler<EventPlayerDisconnect>((@e, @i) =>
        {
            if (@e.Userid != null)
            {
                _activeSessions.TryRemove(@e.Userid.Slot, out _);
                _waitingForInput.TryRemove(@e.Userid.SteamID, out _);
                _lastInputUsage.TryRemove(@e.Userid.SteamID, out _);
            }
            return HookResult.Continue;
        });

        RegisterEventHandler<EventPlayerConnectFull>((@e, @i) =>
        {
            if (@e.Userid != null)
            {
                _activeSessions.TryRemove(@e.Userid.Slot, out _);
                _waitingForInput.TryRemove(@e.Userid.SteamID, out _);
                _lastInputUsage.TryRemove(@e.Userid.SteamID, out _);
            }
            return HookResult.Continue;
        });
    }

    [ConsoleCommand("hlx", "Dashboard")]
    public void OnHlxCommand(CCSPlayerController? player, CommandInfo info)
    {
        if (player == null || !player.IsValid) return;
        ulong steamId = player.SteamID;
        int slot = player.Slot;
        _waitingForInput.TryRemove(steamId, out _);

        string syncText = "Synchronizing...";
        try { syncText = Localizer["status.sync"]; }
        catch (Exception ex)
        {
            Console.WriteLine($"[HLStatsX_CS2] Localizer error: {ex.Message}");
        }
        player.PrintToCenterHtml(syncText);

        _ = HandleHlxCommandAsync(player, steamId, slot);
    }

    private async Task HandleHlxCommandAsync(CCSPlayerController player, ulong steamId, int slot)
    {
        try
        {
            var stats = await GetPlayerDataAsync(steamId, slot);
            Server.NextFrame(() =>
            {
                var p = Utilities.GetPlayerFromSlot(slot);
                if (p == null || !p.IsValid)
                    return;
                if (stats == null)
                {
                    p.PrintToChat(Localizer["error.general"]);
                    return;
                }
                stats.LastActivity = DateTime.Now;
                _activeSessions[slot] = stats;
                BuildAndOpenMainMenu(p);
            });
        }
        catch (Exception ex)
        {
            Console.WriteLine($"[HLStatsX_CS2] HlxCommand error: {ex.Message}");
            Server.NextFrame(() =>
            {
                if (player.IsValid)
                    player.PrintToChat(Localizer["error.general"]);
            });
        }
    }

    private HookResult OnPlayerSay(CCSPlayerController? player, CommandInfo info)
    {
        if (player == null || !player.IsValid) return HookResult.Continue;

        string text = info.ArgString.Trim().Trim('"');

        if (HandleChatCommands(player, text))
        {
            return HookResult.Handled;
        }

        if (_waitingForInput.TryGetValue(player.SteamID, out var input))
        {
            RefreshActivity(player);
            if (_lastInputUsage.TryGetValue(player.SteamID, out var lastUsed))
            {
                var diff = DateTime.Now - lastUsed;
                if (diff.TotalSeconds < Config.CooldownSeconds)
                {
                    int remaining = Config.CooldownSeconds - (int)diff.TotalSeconds;
                    Console.WriteLine($"[HLStatsX_CS2] Player {player.PlayerName} tried input during cooldown ({remaining}s left).");
                    player.PrintToChat(Localizer["error.input_cooldown", remaining]);
                    return HookResult.Handled;
                }
            }

            text = info.ArgString.Trim().Trim('"');
            if (text.Length > 512)
            {
                player.PrintToChat(Localizer["error.too_long"]);
                _waitingForInput.TryRemove(player.SteamID, out _);
                ShowEditProfileMenu(player);
                return HookResult.Handled;
            }
            
            if (text.Equals("cancel", StringComparison.OrdinalIgnoreCase) ||
                text.Equals("stop", StringComparison.OrdinalIgnoreCase))
            {
                _waitingForInput.TryRemove(player.SteamID, out _);
                player.PrintToChat(Localizer["status.canceled"]);
                ShowEditProfileMenu(player);
                return HookResult.Handled;
            }

            if (input.Field == "fullName")
            {
                text = SanitizeText(text, 100);

                if (string.IsNullOrWhiteSpace(text))
                {
                    player.PrintToChat(Localizer["error.name"]);
                    _waitingForInput.TryRemove(player.SteamID, out _);
                    ShowEditProfileMenu(player);
                    return HookResult.Handled;
                }
            }
            if (input.Field == "email")
            {
                text = text.Trim();

                if (!IsValidEmail(text))
                {
                    player.PrintToChat(Localizer["error.email"]);
                    _waitingForInput.TryRemove(player.SteamID, out _);
                    ShowEditProfileMenu(player);
                    return HookResult.Handled;
                }

                if (text.Length > 64)
                    text = text[..64];
            }
            if (input.Field == "homepage")
            {
                if (!IsValidHomepage(text, out var cleanUrl))
                {
                    player.PrintToChat(Localizer["error.homepage"]);
                    _waitingForInput.TryRemove(player.SteamID, out _);
                    ShowEditProfileMenu(player);
                    return HookResult.Handled;
                }

                text = cleanUrl;
                if (text.Length > 64)
                    text = text[..64];
            }
            _ = UpdateDbField(0, input.Field, text, player.Slot);
            _waitingForInput.TryRemove(player.SteamID, out _);
            _lastInputUsage[player.SteamID] = DateTime.Now;
            return HookResult.Handled;
        }
        return HookResult.Continue;
    }

    private void BuildAndOpenMainMenu(CCSPlayerController player)
    {
        if (!_activeSessions.TryGetValue(player.Slot, out var session)) return;
        _waitingForInput.TryRemove(player.SteamID, out _);
        session.LastActivity = DateTime.Now;
        string trend = session.LastSkillChange > 0 ? $"<font color='green'>(▲{session.LastSkillChange})</font>" : session.LastSkillChange < 0 ? $"<font color='red'>(▼{Math.Abs(session.LastSkillChange)})</font>" : "<font color='gray'>(–)</font>";
        double accuracy = session.Accuracy;
        double kd = session.Deaths > 0 ? (double)session.Kills / session.Deaths : session.Kills;
        string multiLineTitle = 
            $"{Localizer["menu.title", $"<font color='yellow'>{Escape(Trunc(player.PlayerName, 20))}</font>"]}<br/>" +
            $"<font color='white'>" +
            $"{Localizer["menu.rank", session.RankPos, Escape(session.RankName)]}<br/>" +
            $"{Localizer["menu.skill", session.Skill, trend]} | K/D: {kd:F2}<br/>" +
            $"{Localizer["menu.totals", session.Kills, session.Headshots]}<br/>" +
            $"{Localizer["menu.accuracy", accuracy.ToString("F1", System.Globalization.CultureInfo.InvariantCulture)]}<br/>" +
            $"___________________________________</font>";
        var menu = new CenterHtmlMenu(multiLineTitle, this);
        menu.AddMenuOption(" -> " + Localizer["menu.next"], (p, opt) => { RefreshActivity(p); BuildAndOpenActionsMenu(p); });
        MenuManager.OpenCenterHtmlMenu(this, player, menu);
    }

    private void BuildAndOpenActionsMenu(CCSPlayerController player)
    {
        if (!_activeSessions.TryGetValue(player.Slot, out var session)) return;
        RefreshActivity(player);

        string title = $"{Localizer["menu.title", $"<font color='yellow'>{Escape(Trunc(player.PlayerName, 20))}</font>"]}";

        var menu = new CenterHtmlMenu(title, this);

        menu.AddMenuOption(Localizer["menu.hof"], (p, opt) => { RefreshActivity(p); _ = ShowTop10(p.Slot); });
        menu.AddMenuOption(Localizer["menu.weapons"], (p, opt) => { RefreshActivity(p); _ = ShowTopWeapons(p.Slot); });
        menu.AddMenuOption(Localizer["menu.victims"], (p, opt) => { RefreshActivity(p); _ = ShowTopVictims(p.Slot); });
        string editLabelWithLine = Localizer["menu.edit_profile"] + "<br/><font color='white'>___________________________________</font>";
        menu.AddMenuOption(editLabelWithLine, (p, opt) => { RefreshActivity(p); ShowEditProfileMenu(p); });
        menu.AddMenuOption(Localizer["menu.back"], (p, opt) => { RefreshActivity(p); BuildAndOpenMainMenu(p); });
        MenuManager.OpenCenterHtmlMenu(this, player, menu);
    }

    private void ShowEditProfileMenu(CCSPlayerController player)
    {
        if (!_activeSessions.TryGetValue(player.Slot, out var session)) return;
        RefreshActivity(player);
        var menu = new CenterHtmlMenu(Localizer["edit.title"], this);

        menu.AddMenuOption(Localizer["edit.name"], (p, opt) => { RefreshActivity(p); _waitingForInput[p.SteamID] = ("fullName", DateTime.Now); p.PrintToChat(Localizer["chat.prompt.name"]); MenuManager.CloseActiveMenu(p); });
        menu.AddMenuOption(Localizer["edit.email"], (p, opt) => { RefreshActivity(p); _waitingForInput[p.SteamID] = ("email", DateTime.Now); p.PrintToChat(Localizer["chat.prompt.email"]); MenuManager.CloseActiveMenu(p); });
            string hideRankingLabel = session.IsHidden == 1
                ? Localizer["menu.hideranking.off"]
                : Localizer["menu.hideranking.on"];
            menu.AddMenuOption(hideRankingLabel, (p, opt) =>
            {
                RefreshActivity(p);
                int newValue = session.IsHidden == 1 ? 0 : 1;
                session.IsHidden = newValue;
                _ = UpdateDbField(0, "hideranking", newValue, p.Slot);
            });

        string homepageWithLine = Localizer["edit.homepage"] + "<br/><font color='white'>___________________________________</font>";
        menu.AddMenuOption(homepageWithLine, (p, opt) => { RefreshActivity(p); _waitingForInput[p.SteamID] = ("homepage", DateTime.Now); p.PrintToChat(Localizer["chat.prompt.homepage"]); MenuManager.CloseActiveMenu(p); });
        menu.AddMenuOption(Localizer["menu.back"], (p, opt) => { RefreshActivity(p); BuildAndOpenMainMenu(p); });
        MenuManager.OpenCenterHtmlMenu(this, player, menu);
    }

    private async Task<HLXSession?> GetPlayerDataAsync(ulong steamId64, int slot)
    {
        string steam2Id = $"{(steamId64 - 76561197960265728) % 2}:{(steamId64 - 76561197960265728) / 2}";
        try
        {
            using var conn = new MySqlConnection(GetConnString());
            await conn.OpenAsync();
            string sql = @"
                SELECT p.playerId, p.skill, p.last_skill_change, p.kills, p.deaths, p.headshots, p.fullName, p.homepage, p.hideranking,
                IFNULL(ROUND((p.hits / NULLIF(p.shots, 0)) * 100, 2), 0) AS accuracy,
                (SELECT COUNT(*) FROM hlstats_Players WHERE game = 'cs2' AND hideranking = 0 AND skill > p.skill) + 1 AS rank_pos,
                (SELECT rankName FROM hlstats_Ranks WHERE game = 'cs2' AND minKills <= p.kills ORDER BY minKills DESC LIMIT 1) AS rank_name
                FROM hlstats_Players p
                JOIN hlstats_PlayerUniqueIds u ON p.playerId = u.playerId
                WHERE u.uniqueId = @sid AND u.game = 'cs2' LIMIT 1";
            using var cmd = new MySqlCommand(sql, conn);
            cmd.Parameters.AddWithValue("@sid", steam2Id);
            using var r = await cmd.ExecuteReaderAsync();
            if (await r.ReadAsync())
            {
                var s = new HLXSession
                {
                    PlayerId = r.GetUInt32("playerId"),
                    Skill = r.GetInt32("skill"),
                    LastSkillChange = r.IsDBNull(r.GetOrdinal("last_skill_change")) ? 0 : r.GetInt32("last_skill_change"),
                    RankPos = r.GetInt32("rank_pos"),
                    RankName = r.IsDBNull(r.GetOrdinal("rank_name")) ? "Recruit" : r.GetString("rank_name"),
                    RealName = r.IsDBNull(r.GetOrdinal("fullName")) ? "" : r.GetString("fullName"),
                    IsHidden = r.GetInt32("hideranking"),
                    Kills = r.GetInt32("kills"),
                    Deaths = r.GetInt32("deaths"),
                    Headshots = r.IsDBNull(r.GetOrdinal("headshots")) ? 0 : r.GetInt32("headshots"),
                    Accuracy = r.IsDBNull(r.GetOrdinal("accuracy")) ? 0 : r.GetDouble("accuracy"),
                };
                return s;
            }
        }
        catch (Exception ex)
        {
            Console.WriteLine($"[HLStatsX_CS2] Error: {ex.Message}");
        }
        return null;
    }

    private void OpenPagedListMenu(CCSPlayerController player, string title, List<string> items, int page, Action<CCSPlayerController> onBack)
    {
        const int maxRows = 6;
        if (items.Count == 0)
        {
            items.Add(Localizer["menu.nodata"]);
        }
        int totalItems = items.Count;

        int itemsPerPage = maxRows - 2;
        if (itemsPerPage < 1) itemsPerPage = 1;

        int totalPages = (totalItems + itemsPerPage - 1) / itemsPerPage;

        if (page < 0) page = 0;
        if (page >= totalPages && totalPages > 0) page = totalPages - 1;

        bool isLastPage = page >= totalPages - 1;

        int startIndex = page * itemsPerPage;
        int endIndex = Math.Min(startIndex + itemsPerPage, totalItems);

        string htmlContent =
            $"{title} <font color='silver'>({page + 1}/{Math.Max(1, totalPages)})</font><br/><font color='white'>";

        for (int i = startIndex; i < endIndex; i++)
        {
            htmlContent += items[i] + "<br/>";
        }

        htmlContent += "___________________________________</font>";

        var menu = new CenterHtmlMenu(htmlContent, this);

        if (!isLastPage)
        {
            menu.AddMenuOption("-> " + Localizer["menu.next"], (p, opt) =>
            {
                RefreshActivity(p);
                OpenPagedListMenu(p, title, items, page + 1, onBack);
            });
        }
        else
        {
            menu.AddMenuOption(Localizer["menu.back"], (p, opt) =>
            {
                RefreshActivity(p);
                onBack(p);
            });
        }
        MenuManager.OpenCenterHtmlMenu(this, player, menu);
    }

    private async Task ShowTop10(int slot)
    {
        var topList = new List<string>();
        try
        {
            using var conn = new MySqlConnection(GetConnString());
            await conn.OpenAsync();
            using var cmd = new MySqlCommand("SELECT lastName, skill FROM hlstats_Players WHERE game = 'cs2' AND hideranking = 0 ORDER BY skill DESC LIMIT 10", conn);
            using var r = await cmd.ExecuteReaderAsync();
            while (await r.ReadAsync())
            {
                string name = r.IsDBNull(0) ? "Unknown" : r.GetString(0);
                topList.Add($"{name}|{System.Convert.ToInt32(r[1])}");
            }
        }
        catch (Exception ex)
        {
            Console.WriteLine($"[HLStatsX_CS2] Error: {ex.Message}");
            if (slot != -1)
            {
                Server.NextFrame(() => {
                    var targetPlayer = Utilities.GetPlayerFromSlot(slot);
                    if (targetPlayer != null && targetPlayer.IsValid)
                        targetPlayer.PrintToChat(Localizer["error.general"]);
                });
            }
            return;
        }

        Server.NextFrame(() =>
        {
            var targetPlayer = Utilities.GetPlayerFromSlot(slot);
            if (targetPlayer == null || !targetPlayer.IsValid) return;
            var formattedList = new List<string>();
            for (int i = 0; i < topList.Count; i++)
            {
                var p = topList[i].Split('|');
                string color = i == 0 ? "gold" : i == 1 ? "silver" : i == 2 ? "orange" : "white";
                formattedList.Add($"<font color='{color}'>{i + 1}. {Escape(Trunc(p[0], 18))} - {p[1]}</font>");
            }
            OpenPagedListMenu(targetPlayer, Localizer["hof.title"], formattedList, 0, (pl) => BuildAndOpenActionsMenu(pl));
        });
    }

    private async Task ShowTopWeapons(int slot)
    {
        if (!_activeSessions.TryGetValue(slot, out var session)) return;
        var weapons = new List<string>();
        try
        {
            using var conn = new MySqlConnection(GetConnString());
            await conn.OpenAsync();
            using var cmd = new MySqlCommand(
                @"SELECT COALESCE(w.name, f.weapon) AS weaponName, COUNT(*) AS kills
                  FROM hlstats_Events_Frags f
                  LEFT JOIN hlstats_Weapons w ON (w.code = f.weapon AND w.game = 'cs2')
                  WHERE f.killerId = @id
                  GROUP BY f.weapon, w.name ORDER BY kills DESC LIMIT 10",
                conn);
            cmd.Parameters.AddWithValue("@id", session.PlayerId);
            using var r = await cmd.ExecuteReaderAsync();
            while (await r.ReadAsync()) weapons.Add($"{r.GetString(0)}|{System.Convert.ToInt32(r[1])}");
        }
        catch (Exception ex)
        {
            Console.WriteLine($"[HLStatsX_CS2] Error: {ex.Message}");
            if (slot != -1)
            {
                Server.NextFrame(() => {
                    var targetPlayer = Utilities.GetPlayerFromSlot(slot);
                    if (targetPlayer != null && targetPlayer.IsValid)
                        targetPlayer.PrintToChat(Localizer["error.general"]);
                });
            }
            return;
        }

        Server.NextFrame(() =>
        {
            var targetPlayer = Utilities.GetPlayerFromSlot(slot);
            if (targetPlayer == null || !targetPlayer.IsValid) return;

            var formattedList = new List<string>();
            for (int i = 0; i < weapons.Count; i++)
            {
                var p = weapons[i].Split('|');
                formattedList.Add($"{i + 1}. {Escape(Trunc(p[0].ToUpper(), 18))} - {p[1]}");
            }

            OpenPagedListMenu(targetPlayer, Localizer["weapons.title"], formattedList, 0, (pl) => BuildAndOpenActionsMenu(pl));
        });
    }

    private async Task ShowTopVictims(int slot)
    {
        if (!_activeSessions.TryGetValue(slot, out var session)) return;
        var victims = new List<string>();
        try
        {
            using var conn = new MySqlConnection(GetConnString());
            await conn.OpenAsync();
            using var cmd = new MySqlCommand(
                @"SELECT p.lastName, COUNT(*) AS cnt 
                  FROM hlstats_Events_Frags f 
                  JOIN hlstats_Players p ON f.victimId = p.playerId 
                  WHERE f.killerId = @id 
                  GROUP BY f.victimId, p.lastName 
                  ORDER BY cnt DESC LIMIT 10",
                conn);
            cmd.Parameters.AddWithValue("@id", session.PlayerId);
            using var r = await cmd.ExecuteReaderAsync();
            while (await r.ReadAsync())
            {
                string name = r.IsDBNull(0) ? "Unknown" : r.GetString(0);
                victims.Add($"{name}|{System.Convert.ToInt32(r[1])}");
            }
        }
        catch (Exception ex)
        {
            Console.WriteLine($"[HLStatsX_CS2] Error: {ex.Message}");
            if (slot != -1)
            {
                Server.NextFrame(() => {
                    var targetPlayer = Utilities.GetPlayerFromSlot(slot);
                    if (targetPlayer != null && targetPlayer.IsValid)
                        targetPlayer.PrintToChat(Localizer["error.general"]);
                });
            }
            return;
        }

        Server.NextFrame(() =>
        {
            var targetPlayer = Utilities.GetPlayerFromSlot(slot);
            if (targetPlayer == null || !targetPlayer.IsValid) return;

            var formattedList = new List<string>();
            for (int i = 0; i < victims.Count; i++)
            {
                var p = victims[i].Split('|');
                formattedList.Add($"{i + 1}. {Escape(Trunc(p[0], 18))} - {p[1]}");
            }
            OpenPagedListMenu(targetPlayer, Localizer["victims.title"], formattedList, 0, (pl) => BuildAndOpenActionsMenu(pl));
        });
    }

    private async Task UpdateDbField(uint pId, string field, object val, int slot = -1)
    {
        try
        {
            if (field is not ("fullName" or "email" or "homepage" or "hideranking")) return;
            using var conn = new MySqlConnection(GetConnString());
            await conn.OpenAsync();
            uint targetId = pId;
            if (slot != -1 && _activeSessions.TryGetValue(slot, out var session)) targetId = session.PlayerId;

            // Fallback: Resolve PlayerId directly from DB via SteamID if session does not exist yet
            if (targetId == 0 && slot != -1)
            {
                var p = Utilities.GetPlayerFromSlot(slot);
                if (p != null && p.IsValid)
                {
                    ulong steamId64 = p.SteamID;
                    string steam2Id = $"{(steamId64 - 76561197960265728) % 2}:{(steamId64 - 76561197960265728) / 2}";
                    using var findCmd = new MySqlCommand("SELECT playerId FROM hlstats_PlayerUniqueIds WHERE uniqueId = @sid AND game = 'cs2' LIMIT 1", conn);
                    findCmd.Parameters.AddWithValue("@sid", steam2Id);
                    var pidObj = await findCmd.ExecuteScalarAsync();
                    if (pidObj != null && Convert.ToUInt32(pidObj) > 0)
                    {
                        targetId = Convert.ToUInt32(pidObj);
                    }
                }
            }

            if (targetId == 0) return;
            string sql = field switch
            {
                "fullName" => "UPDATE hlstats_Players SET fullName = @v WHERE playerId = @id",
                "email" => "UPDATE hlstats_Players SET email = @v WHERE playerId = @id",
                "homepage" => "UPDATE hlstats_Players SET homepage = @v WHERE playerId = @id",
                "hideranking" => "UPDATE hlstats_Players SET hideranking = @v WHERE playerId = @id",
                _ => throw new InvalidOperationException("Invalid field")
            };

            using var cmd = new MySqlCommand(sql, conn);
            cmd.Parameters.AddWithValue("@id", targetId);

            string displayValue;

            if (field == "hideranking")
            {
                int intVal = Convert.ToInt32(val);
                  cmd.Parameters.Add("@v", MySqlDbType.Int32).Value = intVal;
                  string visibilityText = intVal == 1
                      ? Localizer["label.hidden"]
                      : Localizer["label.visible"];

                  displayValue = Localizer["status.visibility", visibilityText]; 
            }
            else
            {
                string safeVal = val?.ToString()?.Trim() ?? "";

                switch (field)
                {
                    case "fullName":
                        if (safeVal.Length > 100)
                            safeVal = safeVal[..100];
                        break;

                    case "email":
                    case "homepage":
                        if (safeVal.Length > 64)
                            safeVal = safeVal[..64];
                        break;
                }
                cmd.Parameters.Add("@v", MySqlDbType.VarChar).Value = safeVal;
                displayValue = safeVal;
            }

            await cmd.ExecuteNonQueryAsync();

            if (slot != -1)
            {
                Server.NextFrame(() =>
                {
                    var targetPlayer = Utilities.GetPlayerFromSlot(slot);
                    if (targetPlayer != null && targetPlayer.IsValid)
                    {
                        targetPlayer.PrintToChat(Localizer["status.updated", displayValue]);
                        Server.NextFrame(() => ShowEditProfileMenu(targetPlayer));
                    }
                });
            }
        }
        catch (Exception ex)
        {
            Console.WriteLine($"[HLStatsX_CS2] Update error: {ex.Message}");
            if (slot != -1)
            {
                Server.NextFrame(() =>
                {
                    var targetPlayer = Utilities.GetPlayerFromSlot(slot);
                    if (targetPlayer != null && targetPlayer.IsValid)
                        targetPlayer.PrintToChat(Localizer["error.general"]);
                });
            }
        }
    }

    private string SanitizeText(string input, int maxLen)
    {
        if (string.IsNullOrWhiteSpace(input)) return "";
        input = input.Trim();

        // length limit
        if (input.Length > maxLen)
            input = input[..maxLen];

        // HTML / XML tags, attribute breaking
        input = Regex.Replace(input, @"[<>""'/\\]", "");

        // non-printable characters
        input = Regex.Replace(input, @"[\x00-\x1F\x7F]", "");

        return input;
    }

    private bool IsValidEmail(string email)
    {
        try
        {
            var addr = new MailAddress(email);
            return addr.Address == email;
        }
        catch
        {
            return false;
        }
    }

    private bool IsValidHomepage(string url, out string cleanUrl)
    {
        cleanUrl = "";

        if (!Uri.TryCreate(url, UriKind.Absolute, out var uri))
            return false;

        if (uri.Scheme != Uri.UriSchemeHttp && uri.Scheme != Uri.UriSchemeHttps)
            return false;

        string host = uri.Host.ToLowerInvariant();

        if (host == "localhost" ||
            host.EndsWith(".local") ||
            host.EndsWith(".lan") ||
            host.EndsWith(".home"))
        {
            return false;
        }

        if (IPAddress.TryParse(host, out var ip))
        {
            // loopback
            if (IPAddress.IsLoopback(ip))
                return false;

            byte[] bytes = ip.GetAddressBytes();

            // IPv4 private domains
            if (bytes.Length == 4)
            {
                // 10.0.0.0/8
                if (bytes[0] == 10)
                    return false;

                // 172.16.0.0 – 172.31.255.255
                if (bytes[0] == 172 && bytes[1] >= 16 && bytes[1] <= 31)
                    return false;

                // 192.168.0.0/16
                if (bytes[0] == 192 && bytes[1] == 168)
                    return false;
            }
            if (bytes.Length == 16)
           {
               if ((bytes[0] & 0xFE) == 0xFC)
                   return false;

               if (bytes[0] == 0xFE && (bytes[1] & 0xC0) == 0x80)
                  return false;
           }
        }
        cleanUrl = uri.GetComponents(
            UriComponents.SchemeAndServer |
            UriComponents.PathAndQuery |
            UriComponents.Fragment,
            UriFormat.UriEscaped
        );
        if (cleanUrl.Length > 64)
            cleanUrl = cleanUrl[..64];

        return true;
    }

    private string GetConnString() => $"Server={Config.DatabaseHost};Port={Config.DatabasePort};User ID={Config.DatabaseUser};Password={Config.DatabasePassword};Database={Config.DatabaseName};SslMode=Preferred;Pooling=true;MaximumPoolSize=20;";
    private string Trunc(string s, int l) => s.Length > l ? s[..(l - 2)] + ".." : s;
    private void RefreshActivity(CCSPlayerController player) { if (_activeSessions.TryGetValue(player.Slot, out var s)) s.LastActivity = DateTime.Now; }
    private string Escape(string s) => System.Net.WebUtility.HtmlEncode(s);

    // ON-TICK PERSISTENT CENTER MESSAGE
    private void OnTick()
    {
        if (_isCenterMessageActive)
        {
            if (DateTime.UtcNow >= _centerMessageEndTime)
            {
                _isCenterMessageActive = false;
                _centerMessageEndTime = DateTime.MinValue;
                _activeCenterHtml = string.Empty;

                foreach (var p in Utilities.GetPlayers())
                {
                    if (p != null && p.IsValid && !p.IsBot && !p.IsHLTV)
                    {
                        p.PrintToCenterHtml(" ");
                    }
                }
            }
            else
            {
                foreach (var p in Utilities.GetPlayers())
                {
                    if (p != null && p.IsValid && !p.IsBot && !p.IsHLTV && !_activeSessions.ContainsKey(p.Slot))
                    {
                        p.PrintToCenterHtml(_activeCenterHtml);
                    }
                }
            }
        }
    }

    // HLstatsX RCON COMMANDS: hlx_say (broadcast) & hlx_psay (targeted)
    [ConsoleCommand("hlx_say", "Send colored broadcast or targeted message")]
    public void CommandHlxSay(CCSPlayerController? caller, CommandInfo info)
    {
        if (caller != null || string.IsNullOrWhiteSpace(info.ArgString)) return;

        string raw = info.ArgString.Trim();
        string cleanMessage = ParseIncomingHlxMessage(raw, out string targetStr);

        if (!string.IsNullOrEmpty(cleanMessage))
        {
            DeliverHlxChatMessage(targetStr, cleanMessage);
        }
    }

    [ConsoleCommand("hlx_psay", "Send private or broadcast colored message")]
    public void CommandHlxPsay(CCSPlayerController? caller, CommandInfo info)
    {
        if (caller != null || string.IsNullOrWhiteSpace(info.ArgString)) return;

        string raw = info.ArgString.Trim();
        string cleanMessage = ParseIncomingHlxMessage(raw, out string targetStr);

        if (!string.IsNullOrEmpty(cleanMessage))
        {
            DeliverHlxChatMessage(targetStr, cleanMessage);
        }
    }

    // Shared intelligent delivery: routes to target player privately if specified, or broadcasts to all
    private void DeliverHlxChatMessage(string targetStr, string cleanMessage)
    {
        string formattedMessage = FormatHlxChatMessage(cleanMessage);

        if (string.IsNullOrWhiteSpace(targetStr))
        {
            Server.PrintToChatAll(formattedMessage);
            return;
        }

        bool isTargetedPlayer = targetStr.StartsWith('#');
        if (isTargetedPlayer)
        {
            targetStr = targetStr[1..];
        }

        CCSPlayerController? targetPlayer = null;

        if (int.TryParse(targetStr, out int targetId))
        {
            targetPlayer = Utilities.GetPlayerFromUserid(targetId);

            if (targetPlayer == null && targetId >= 0 && targetId < 64)
            {
                targetPlayer = Utilities.GetPlayerFromSlot(targetId);
            }
        }

        if (targetPlayer != null && targetPlayer.IsValid)
        {
            targetPlayer.PrintToChat(formattedMessage);
        }
        else if (!isTargetedPlayer)
        {
            Server.PrintToChatAll(formattedMessage);
        }
    }

    [ConsoleCommand("hlx_csay", "Send center screen announcement to all players")]
    public void CommandHlxCsay(CCSPlayerController? caller, CommandInfo info)
    {
        if (caller != null || string.IsNullOrWhiteSpace(info.ArgString)) return;

        string raw = info.ArgString.Trim();
        string cleanMessage = ParseIncomingHlxMessage(raw, out _);

        if (string.IsNullOrEmpty(cleanMessage)) return;

        string htmlBody = ResolveCenterHtmlColors(cleanMessage);
        if (htmlBody.StartsWith("</font>"))
        {
            htmlBody = htmlBody.Substring(7);
        }

        _activeCenterHtml = $"<font color='#2ecc71' size='30'><b>[HLstatsX]</b></font><br>" +
                            $"<font color='#ffffff' size='22'>{htmlBody}</font>";

        _isCenterMessageActive = true;
        _centerMessageEndTime = DateTime.UtcNow.AddSeconds(4);
    }

    // Helper: Completely removes the leading target ('0', '#14') and any legacy dummy quotes ('""', '" "', "''", "' '")
    private string ParseIncomingHlxMessage(string rawArgString, out string target)
    {
        target = "";
        string text = rawArgString.Trim();

        // Regex matches:
        // 1. Optional target (e.g. '0', '14', '#14')
        // 2. Any dummy quote parameters (e.g. '""', '" "', "''", "' '")
        // 3. The actual message content
        var match = Regex.Match(text, @"^(?:[""']?(?<target>#\d+)[""']?\s*)?(?:[""']\s*[""']\s*)*(?<message>.*)$", RegexOptions.Singleline);

        if (match.Success)
        {
            target = match.Groups["target"].Value.Trim();
            text = match.Groups["message"].Value.Trim();
        }

        text = StripOuterQuotes(text);

        text = Regex.Replace(text, @"^0\s*[""']\s*[""']\s*", "").Trim();
        text = StripOuterQuotes(text);

        return text;
    }

    private string StripOuterQuotes(string str)
    {
        str = str.Trim();
        while (str.Length >= 2 && ((str.StartsWith("\"") && str.EndsWith("\"")) || (str.StartsWith("'") && str.EndsWith("'"))))
        {
            str = str.Substring(1, str.Length - 2).Trim();
        }
        return str;
    }

    // Helper: Formats chat message with rich CS2 colors (fixed hyphen boundary collision)
    private string FormatHlxChatMessage(string message)
    {
        string formatted = ResolveChatColors(message);

        formatted = Regex.Replace(formatted, @"\b(Counter-Terrorists?|CT|Terrorists?)\b", m =>
        {
            string val = m.Value;
            if (val.StartsWith("Counter-", StringComparison.OrdinalIgnoreCase) || val.Equals("CT", StringComparison.OrdinalIgnoreCase))
            {
                return $"{ChatColors.LightBlue}{val}{ChatColors.Default}";
            }
            return $"{ChatColors.Gold}{val}{ChatColors.Default}";
        }, RegexOptions.IgnoreCase);

        formatted = Regex.Replace(formatted, @"\b([0-9,]+)\s+(points?)\b", $"{ChatColors.Green}$1 $2{ChatColors.Default}", RegexOptions.IgnoreCase);
        formatted = Regex.Replace(formatted, @"(\([0-9,]+\s*points?\))", $"{ChatColors.Green}$1{ChatColors.Default}", RegexOptions.IgnoreCase);
        formatted = Regex.Replace(formatted, @"\b(connected from|has connected from)\b", $"{ChatColors.LightBlue}$1{ChatColors.Default}", RegexOptions.IgnoreCase);

        return $" {ChatColors.Green}[HLstatsX]{ChatColors.Default} {formatted}";
    }

    // HLstatsX IN-GAME CHAT COMMANDS (/hlx_set, /hlx_hideranking, !hlx)
    private bool HandleChatCommands(CCSPlayerController player, string text)
    {
        if (string.IsNullOrWhiteSpace(text)) return false;

        string cmd = text;
        if (cmd.StartsWith("/") || cmd.StartsWith("!"))
            cmd = cmd.Substring(1);

        cmd = cmd.Trim();

        // 1. Dashboard trigger via chat (!hlx, /hlx, hlx)
        if (cmd.Equals("hlx", StringComparison.OrdinalIgnoreCase) || cmd.Equals("hlstats", StringComparison.OrdinalIgnoreCase))
        {
            OnHlxCommand(player, null!);
            return true;
        }

        // 2. Hide Ranking toggle (/hlx_hideranking)
        if (cmd.Equals("hlx_hideranking", StringComparison.OrdinalIgnoreCase))
        {
            HandleHlxHideRankingChat(player);
            return true;
        }

        // 3. Profile setting (/hlx_set option value)
        if (cmd.StartsWith("hlx_set", StringComparison.OrdinalIgnoreCase))
        {
            HandleHlxSetChat(player, cmd);
            return true;
        }

        return false;
    }

    private void HandleHlxHideRankingChat(CCSPlayerController player)
    {
        _ = HandleHlxHideRankingChatAsync(player);
    }

    private async Task HandleHlxHideRankingChatAsync(CCSPlayerController player)
    {
        ulong steamId64 = player.SteamID;
        int slot = player.Slot;
        string steam2Id = $"{(steamId64 - 76561197960265728) % 2}:{(steamId64 - 76561197960265728) / 2}";

        try
        {
            using var conn = new MySqlConnection(GetConnString());
            await conn.OpenAsync();

            uint playerId = 0;
            int currentHidden = 0;

            string selectSql = @"
                SELECT p.playerId, p.hideranking 
                FROM hlstats_Players p
                JOIN hlstats_PlayerUniqueIds u ON p.playerId = u.playerId
                WHERE u.uniqueId = @sid AND u.game = 'cs2' LIMIT 1";

            using (var selectCmd = new MySqlCommand(selectSql, conn))
            {
                selectCmd.Parameters.AddWithValue("@sid", steam2Id);
                using var reader = await selectCmd.ExecuteReaderAsync();
                if (await reader.ReadAsync())
                {
                    playerId = reader.GetUInt32("playerId");
                    currentHidden = reader.GetInt32("hideranking");
                }
            }

            if (playerId == 0) return;

            int newHidden = currentHidden == 1 ? 0 : 1;

            using (var updateCmd = new MySqlCommand("UPDATE hlstats_Players SET hideranking = @v WHERE playerId = @id", conn))
            {
                updateCmd.Parameters.AddWithValue("@v", newHidden);
                updateCmd.Parameters.AddWithValue("@id", playerId);
                await updateCmd.ExecuteNonQueryAsync();
            }

            if (_activeSessions.TryGetValue(slot, out var session) && session != null)
            {
                session.IsHidden = newHidden;
            }

            Server.NextFrame(() =>
            {
                var p = Utilities.GetPlayerFromSlot(slot);
                if (p != null && p.IsValid)
                {
                    string statusText = newHidden == 1 
                        ? $"\u0007{Localizer["label.hidden"]}" 
                        : $"\u0004{Localizer["label.visible"]}";

                    p.PrintToChat(Localizer["chat.hideranking.status", statusText]);
                }
            });
        }
        catch (Exception ex)
        {
            Console.WriteLine($"[HLStatsX_CS2] HideRanking error: {ex.Message}");
        }
    }

    private void HandleHlxSetChat(CCSPlayerController player, string fullCommand)
    {
        if (_lastInputUsage.TryGetValue(player.SteamID, out var lastUsed))
        {
            var diff = DateTime.Now - lastUsed;
            if (diff.TotalSeconds < Config.CooldownSeconds)
            {
                int remaining = Config.CooldownSeconds - (int)diff.TotalSeconds;
                player.PrintToChat(Localizer["error.input_cooldown", remaining]);
                return;
            }
        }

        string[] parts = fullCommand.Split(' ', 3, StringSplitOptions.RemoveEmptyEntries);
        if (parts.Length < 3)
        {
            player.PrintToChat(Localizer["chat.set.syntax"]);
            return;
        }

        string option = parts[1].ToLowerInvariant();
        string value = parts[2].Trim();

        string dbField;
        string validatedValue;

        switch (option)
        {
            case "realname":
                dbField = "fullName";
                validatedValue = SanitizeText(value, 100);
                if (string.IsNullOrWhiteSpace(validatedValue))
                {
                    player.PrintToChat(Localizer["error.name"]);
                    return;
                }
                break;

            case "email":
                dbField = "email";
                if (!IsValidEmail(value))
                {
                    player.PrintToChat(Localizer["error.email"]);
                    return;
                }
                validatedValue = value.Length > 64 ? value[..64] : value;
                break;

            case "homepage":
                dbField = "homepage";
                if (!IsValidHomepage(value, out var cleanUrl))
                {
                    player.PrintToChat(Localizer["error.homepage"]);
                    return;
                }
                validatedValue = cleanUrl.Length > 64 ? cleanUrl[..64] : cleanUrl;
                break;

            default:
                player.PrintToChat(Localizer["chat.set.unknown", option]);
                return;
        }

        _ = UpdateDbField(0, dbField, validatedValue, player.Slot);
        _lastInputUsage[player.SteamID] = DateTime.Now;
        player.PrintToChat(Localizer["chat.set.success", option, validatedValue]);
    }

    private string ResolveChatColors(string message)
    {
        return message
            .Replace("{DEFAULT}", $"{ChatColors.Default}")
            .Replace("{WHITE}", $"{ChatColors.White}")
            .Replace("{RED}", $"{ChatColors.Red}")
            .Replace("{LIGHTRED}", $"{ChatColors.LightRed}")
            .Replace("{DARKRED}", $"{ChatColors.DarkRed}")
            .Replace("{GREEN}", $"{ChatColors.Green}")
            .Replace("{LIME}", $"{ChatColors.Lime}")
            .Replace("{OLIVE}", $"{ChatColors.Olive}")
            .Replace("{BLUE}", $"{ChatColors.Blue}")
            .Replace("{DARKBLUE}", $"{ChatColors.DarkBlue}")
            .Replace("{PURPLE}", $"{ChatColors.Purple}")
            .Replace("{MAGENTA}", $"{ChatColors.Magenta}")
            .Replace("{GOLD}", $"{ChatColors.Gold}")
            .Replace("{YELLOW}", $"{ChatColors.Yellow}")
            .Replace("{GREY}", $"{ChatColors.Grey}")
            .Replace("{GRAY}", $"{ChatColors.Grey}");
    }

    private string ResolveCenterHtmlColors(string message)
    {
        return message
            .Replace("{DEFAULT}", "</font><font color='#ffffff' size='22'>")
            .Replace("{WHITE}", "</font><font color='#ffffff' size='22'>")
            .Replace("{GREEN}", "</font><font color='#2ecc71' size='22'>")
            .Replace("{LIME}", "</font><font color='#a4d007' size='22'>")
            .Replace("{LIGHTGREEN}", "</font><font color='#a4d007' size='22'>")
            .Replace("{RED}", "</font><font color='#e74c3c' size='22'>")
            .Replace("{LIGHTRED}", "</font><font color='#ff6b6b' size='22'>")
            .Replace("{DARKRED}", "</font><font color='#990000' size='22'>")
            .Replace("{BLUE}", "</font><font color='#3498db' size='22'>")
            .Replace("{LIGHTBLUE}", "</font><font color='#66c0f4' size='22'>")
            .Replace("{DARKBLUE}", "</font><font color='#1e3799' size='22'>")
            .Replace("{PURPLE}", "</font><font color='#9b59b6' size='22'>")
            .Replace("{MAGENTA}", "</font><font color='#e056fd' size='22'>")
            .Replace("{GOLD}", "</font><font color='#f1c40f' size='22'>")
            .Replace("{YELLOW}", "</font><font color='#f1c40f' size='22'>")
            .Replace("{GREY}", "</font><font color='#aaaaaa' size='22'>")
            .Replace("{GRAY}", "</font><font color='#aaaaaa' size='22'>");
    }
}