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
    public override string ModuleVersion => "1.0";
    public override string ModuleAuthor => "lovasatt";
    public override string ModuleDescription => "Integrates HLStatsX with CS2 for viewing and updating player stats via in-game menu.";

    public HLXConfig Config { get; set; } = new();
    private readonly ConcurrentDictionary<int, HLXSession> _activeSessions = new();
    private readonly ConcurrentDictionary<ulong, string> _waitingForInput = new();
    private readonly ConcurrentDictionary<ulong, DateTime> _lastInputUsage = new();

    public void OnConfigParsed(HLXConfig config) => Config = config;

    public override void Load(bool hotReload)
    {
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
                        if (p != null) _waitingForInput.TryRemove(p.SteamID, out _);
                        if (p != null && p.IsValid) MenuManager.CloseActiveMenu(p);
                        _activeSessions.TryRemove(slot, out _);
                    }
                }
            }
        }, TimerFlags.REPEAT);

        AddCommandListener("say", OnPlayerSay);
        AddCommandListener("say_team", OnPlayerSay);

        RegisterEventHandler<EventRoundStart>((@e, @i) =>
        {
            foreach (var p in Utilities.GetPlayers())
            {
                _waitingForInput.TryRemove(p.SteamID, out _);
            }
            return HookResult.Continue;
        });

        RegisterEventHandler<EventPlayerDisconnect>((@e, @i) =>
        {
            if (@e.Userid != null)
            {
                _activeSessions.TryRemove(@e.Userid.Slot, out _);
                _waitingForInput.TryRemove(@e.Userid.SteamID, out _);
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
                    p.PrintToChat(Localizer["error.name"]);
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
                    player.PrintToChat("An error occurred, please try again!");
            });
        }
    }

    private HookResult OnPlayerSay(CCSPlayerController? player, CommandInfo info)
    {
        if (player == null || !player.IsValid) return HookResult.Continue;
        if (_waitingForInput.TryGetValue(player.SteamID, out var field))
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

            string text = info.ArgString.Trim().Trim('"');
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

            if (field == "fullName")
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
            if (field == "email")
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
            if (field == "homepage")
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
            _ = UpdateDbField(0, field, text, player);
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
        string multiLineTitle = 
            $"{Localizer["menu.title", $"<font color='yellow'>{Escape(Trunc(player.PlayerName, 20))}</font>"]}<br/>" +
            $"<font color='white'>" +
            $"{Localizer["menu.rank", session.RankPos, Escape(session.RankName)]}<br/>" +
            $"{Localizer["menu.skill", session.Skill, trend]}<br/>" +
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

        menu.AddMenuOption(Localizer["menu.hof"], (p, opt) => { RefreshActivity(p); _ = ShowTop10(p); });
        menu.AddMenuOption(Localizer["menu.weapons"], (p, opt) => { RefreshActivity(p); _ = ShowTopWeapons(p); });
        menu.AddMenuOption(Localizer["menu.victims"], (p, opt) => { RefreshActivity(p); _ = ShowTopVictims(p); });
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

        menu.AddMenuOption(Localizer["edit.name"], (p, opt) => { RefreshActivity(p); _waitingForInput[p.SteamID] = "fullName"; p.PrintToChat(Localizer["chat.prompt.name"]); MenuManager.CloseActiveMenu(p); });
        menu.AddMenuOption(Localizer["edit.email"], (p, opt) => { RefreshActivity(p); _waitingForInput[p.SteamID] = "email"; p.PrintToChat(Localizer["chat.prompt.email"]); MenuManager.CloseActiveMenu(p); });
            string hideRankingLabel = session.IsHidden == 1
                ? Localizer["menu.hideranking.off"]
                : Localizer["menu.hideranking.on"];
            menu.AddMenuOption(hideRankingLabel, (p, opt) =>
            {
                RefreshActivity(p);
                int newValue = session.IsHidden == 1 ? 0 : 1;
                session.IsHidden = newValue;
                _ = UpdateDbField(0, "hideranking", newValue, p);
            });

        string homepageWithLine = Localizer["edit.homepage"] + "<br/><font color='white'>___________________________________</font>";
        menu.AddMenuOption(homepageWithLine, (p, opt) => { RefreshActivity(p); _waitingForInput[p.SteamID] = "homepage"; p.PrintToChat(Localizer["chat.prompt.homepage"]); MenuManager.CloseActiveMenu(p); });
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
                (SELECT IFNULL(ROUND((SUM(hits) / NULLIF(SUM(shots),0)) * 100, 2) ,0) FROM hlstats_Events_Statsme WHERE playerId = p.playerId) AS accuracy,
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
            items.Add("<font color='gray'>No data available.</font>");
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

    private async Task ShowTop10(CCSPlayerController player)
    {
        var topList = new List<string>();
        try
        {
            using var conn = new MySqlConnection(GetConnString());
            await conn.OpenAsync();
            using var cmd = new MySqlCommand("SELECT lastName, skill FROM hlstats_Players WHERE game = 'cs2' AND hideranking = 0 ORDER BY skill DESC LIMIT 10", conn);
            using var r = await cmd.ExecuteReaderAsync();
            while (await r.ReadAsync()) topList.Add($"{r.GetString(0)}|{System.Convert.ToInt32(r[1])}");
        }
        catch (Exception ex)
        {
            Console.WriteLine($"[HLStatsX_CS2] Error: {ex.Message}");
            if (player != null && player.IsValid)
            {
                Server.NextFrame(() => player.PrintToChat("An error occurred, please try again!"));
            }
            return;
        }

        Server.NextFrame(() =>
        {
            if (player == null || !player.IsValid) return;
            var formattedList = new List<string>();
            for (int i = 0; i < topList.Count; i++)
            {
                var p = topList[i].Split('|');
                string color = i == 0 ? "gold" : i == 1 ? "silver" : i == 2 ? "orange" : "white";
                formattedList.Add($"<font color='{color}'>{i + 1}. {Escape(Trunc(p[0], 18))} - {p[1]}</font>");
            }
            OpenPagedListMenu(player, Localizer["hof.title"], formattedList, 0, (pl) => BuildAndOpenActionsMenu(pl));
        });
    }

    private async Task ShowTopWeapons(CCSPlayerController player)
    {
        if (!_activeSessions.TryGetValue(player.Slot, out var session)) return;
        var weapons = new List<string>();
        try
        {
            using var conn = new MySqlConnection(GetConnString());
            await conn.OpenAsync();
            using var cmd = new MySqlCommand(
                @"SELECT f.weapon, COUNT(*) AS kills
                  FROM hlstats_Events_Frags f
                  LEFT JOIN hlstats_Weapons w ON w.code = f.weapon
                  WHERE f.killerId = @id AND (w.game = 'cs2' OR w.weaponId IS NULL)
                  GROUP BY f.weapon ORDER BY kills DESC LIMIT 10",
                conn);
            cmd.Parameters.AddWithValue("@id", session.PlayerId);
            using var r = await cmd.ExecuteReaderAsync();
            while (await r.ReadAsync()) weapons.Add($"{r.GetString(0)}|{System.Convert.ToInt32(r[1])}");
        }
        catch (Exception ex)
        {
            Console.WriteLine($"[HLStatsX_CS2] Error: {ex.Message}");
            if (player != null && player.IsValid)
            {
                Server.NextFrame(() => player.PrintToChat("An error occurred, please try again!"));
            }
            return;
        }

        Server.NextFrame(() =>
        {
            if (player == null || !player.IsValid) return;

            var formattedList = new List<string>();
            for (int i = 0; i < weapons.Count; i++)
            {
                var p = weapons[i].Split('|');
                formattedList.Add($"{i + 1}. {Escape(Trunc(p[0].ToUpper(), 18))} - {p[1]}");
            }

            OpenPagedListMenu(player, Localizer["weapons.title"], formattedList, 0, (pl) => BuildAndOpenActionsMenu(pl));
        });
    }

    private async Task ShowTopVictims(CCSPlayerController player)
    {
        if (!_activeSessions.TryGetValue(player.Slot, out var session)) return;
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
            while (await r.ReadAsync()) victims.Add($"{r.GetString(0)}|{System.Convert.ToInt32(r[1])}");
        }
        catch (Exception ex)
        {
            Console.WriteLine($"[HLStatsX_CS2] Error: {ex.Message}");
            if (player != null && player.IsValid)
            {
                Server.NextFrame(() => player.PrintToChat("An error occurred, please try again!"));
            }
            return;
        }

        Server.NextFrame(() =>
        {
            if (player == null || !player.IsValid) return;

            var formattedList = new List<string>();
            for (int i = 0; i < victims.Count; i++)
            {
                var p = victims[i].Split('|');
                formattedList.Add($"{i + 1}. {Escape(Trunc(p[0], 18))} - {p[1]}");
            }
            OpenPagedListMenu(player, Localizer["victims.title"], formattedList, 0, (pl) => BuildAndOpenActionsMenu(pl));
        });
    }

    private async Task UpdateDbField(uint pId, string field, object val, CCSPlayerController? p = null)
    {
        try
        {
            if (field is not ("fullName" or "email" or "homepage" or "hideranking")) return;
            using var conn = new MySqlConnection(GetConnString());
            await conn.OpenAsync();
            uint targetId = pId;
            if (p != null && _activeSessions.TryGetValue(p.Slot, out var session)) targetId = session.PlayerId;

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

            if (p != null)
            {
                Server.NextFrame(() =>
                {
                    if (p.IsValid)
                    {
                        p.PrintToChat(Localizer["status.updated", displayValue]);
                        ShowEditProfileMenu(p);
                    }
                });
            }
        }
        catch (Exception ex)
        {
            Console.WriteLine($"[HLStatsX_CS2] Update error: {ex.Message}");
            if (p != null && p.IsValid)
            {
                Server.NextFrame(() => p.PrintToChat("An error occurred, please try again!"));
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

        if (host == "localhost")
            return false;

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

    private string GetConnString() => $"Server={Config.DatabaseHost};Port={Config.DatabasePort};User ID={Config.DatabaseUser};Password={Config.DatabasePassword};Database={Config.DatabaseName};SslMode=None;";
    private string Trunc(string s, int l) => s.Length > l ? s[..(l - 2)] + ".." : s;
    private void RefreshActivity(CCSPlayerController player) { if (_activeSessions.TryGetValue(player.Slot, out var s)) s.LastActivity = DateTime.Now; }
    private string Escape(string s) => System.Net.WebUtility.HtmlEncode(s);
}