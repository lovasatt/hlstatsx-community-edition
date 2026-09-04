using CounterStrikeSharp.API;
using CounterStrikeSharp.API.Core;
using CounterStrikeSharp.API.Core.Attributes;
using CounterStrikeSharp.API.Modules.Utils;
using CounterStrikeSharp.API.Modules.Entities;
using CounterStrikeSharp.API.Modules.Cvars;
using System.Net;
using System.Net.Sockets;
using System.Text;
using System.Text.Json.Serialization;

namespace HLStatsX_SuperLogs;

public class HLStatsXConfig : BasePluginConfig
{
        [JsonPropertyName("HLStats_Host")]
        public string Host { get; set; } = "127.0.0.1"; //IP address of the HLStatsX Daemon or UDP Forwarder

        [JsonPropertyName("HLStats_Port")]
        public int Port { get; set; } = 27500; //Port of the HLStatsX Daemon or UDP Forwarder

        [JsonPropertyName("Enable_Logging")]
        public bool Enable { get; set; } = true; //Enable UDP logging

        [JsonPropertyName("Use_Proxy_Forwarding")]
        public bool UseProxyForwarding { get; set; } = true; //Enable HLStatsX PROXY header forwarding (replaces UDP Forwarder PROXY_KEY function)

        [JsonPropertyName("Proxy_Key")]
        public string ProxyKey { get; set; } = "d7fe18f9c4d10180f2aa6393"; //HLStatsX:CE PROXY_KEY secret, dummy_key, change it!

        [JsonPropertyName("GameServer_IP")]
        public string GameServerIP { get; set; } = "192.168.1.100"; //Game server IP address reported to HLStatsX:CE

        [JsonPropertyName("GameServer_Port")]
        public int GameServerPort { get; set; } = 27015; //Game server port reported to HLStatsX:CE

}

[MinimumApiVersion(80)]
public class HLStatsX_SuperLogs : BasePlugin, IPluginConfig<HLStatsXConfig>
{
    public override string ModuleName => "HLStatsX:CE SuperLogs CS2";
    public override string ModuleVersion => "2.5";
    public override string ModuleAuthor => "lovasatt";

    public HLStatsXConfig Config { get; set; } = new HLStatsXConfig();
    
    private readonly Dictionary<int, Dictionary<string, WeaponStats>> _playerStats = new();
    private readonly Dictionary<int, string> _lastActiveWeapon = new();
    private UdpClient? _udpClient;
    private IPEndPoint? _remoteEndPoint;
    private bool _isWarmup = false;
    private DateTime _lastNetworkRetry = DateTime.MinValue;

    public void OnConfigParsed(HLStatsXConfig config)
    {
        this.Config = config;
        if (Config.UseProxyForwarding)
        {
            bool error = false;
            if (string.IsNullOrWhiteSpace(Config.ProxyKey))
            {
                Console.WriteLine("[SuperLogs] ERROR: Proxy_Key is invalid!");
                error = true;
            }
            if (string.IsNullOrWhiteSpace(Config.GameServerIP))
            {
                Console.WriteLine("[SuperLogs] ERROR: GameServer_IP is empty!");
                error = true;
            }
            else if (!IPAddress.TryParse(Config.GameServerIP, out _))
            {
                Console.WriteLine("[SuperLogs] ERROR: Invalid GameServer_IP!");
                error = true;
            }
            if (Config.GameServerPort <= 0 || Config.GameServerPort > 65535)
            {
                Console.WriteLine("[SuperLogs] ERROR: Invalid GameServer_Port!");
                error = true;
            }
            if (error)
            {
                Console.WriteLine("[SuperLogs] Proxy forwarding disabled.");
                Config.UseProxyForwarding = false;
            }
        }
        InitNetwork();
    }

    public override void Load(bool hotReload)
    {

        RegisterListener<Listeners.OnMapStart>((mapName) => 
        { 
            _isWarmup = IsWarmup(); 
            LogToUDP($"Started map \"{Server.MapName}\"", true); 
        });
        RegisterEventHandler<EventPlayerHurt>(OnPlayerHurt);
        RegisterEventHandler<EventPlayerDeath>(OnPlayerDeath);
        RegisterEventHandler<EventItemEquip>(OnItemEquip);
        RegisterEventHandler<EventWeaponFire>(OnWeaponFire);
        RegisterEventHandler<EventRoundStart>(OnRoundStart);
        RegisterEventHandler<EventPlayerDisconnect>(OnPlayerDisconnect);
        RegisterEventHandler<EventPlayerConnectFull>(OnPlayerConnectFull);
        RegisterEventHandler<EventPlayerTeam>(OnPlayerTeam);
        RegisterEventHandler<EventWarmupEnd>((@e, @i) => 
        { 
            _isWarmup = false; 
            return HookResult.Continue; 
        });

        if (hotReload)
        {
            CheckWarmupStatus();
            foreach (var player in Utilities.GetPlayers())
            {
                if (player.IsValid && !player.IsBot) InitPlayerStats(player.Slot);
            }
        }
    }

    public override void Unload(bool hotReload)
    {
        _udpClient?.Dispose();
        _udpClient = null;
    }

    private void InitNetwork()
    {
        try
        {
            _udpClient?.Dispose();
            _udpClient = new UdpClient();
            _remoteEndPoint = null;
            if (string.IsNullOrWhiteSpace(Config.Host))
            {
                Console.WriteLine("[SuperLogs] HLStats_Host is empty.");
                return;
            }
            string host = Config.Host.Trim();

            if (host.Equals("localhost", StringComparison.OrdinalIgnoreCase))
            {
                _remoteEndPoint = new IPEndPoint(IPAddress.Loopback, Config.Port);
            }
            else if (IPAddress.TryParse(host, out var ip))
            {
                _remoteEndPoint = new IPEndPoint(ip, Config.Port);
            }
            else
            {
                Console.WriteLine($"[SuperLogs] Invalid IP address format: '{Config.Host}'. Only raw IPv4 addresses (or 'localhost') are supported.");
            }
        }
        catch (Exception ex)
        {
            _remoteEndPoint = null;
            Console.WriteLine($"[SuperLogs] Network Error: {ex.Message}");
        }
    }

    private string GetVerifiedWeaponName(CCSPlayerController? player, string eventWeapon)
    {
        string weapon = eventWeapon.ToLower().Replace("weapon_", "").Replace("_off", "");

        if (weapon.Contains("incgrenade") || weapon.Contains("molotov") || weapon == "inferno")
        {
            return (player?.TeamNum == 3) ? "firebomb" : "inferno";
        }

        if (weapon == "knife")
        {
            return (player?.TeamNum == 3) ? "knife" : "knife_t";
        }

        if (player != null && player.IsValid && player.PlayerPawn?.Value != null)
        {
            var activeWeapon = player.PlayerPawn.Value.WeaponServices?.ActiveWeapon.Value;
            if (activeWeapon != null)
            {
                uint index = activeWeapon.AttributeManager.Item.ItemDefinitionIndex;
                return index switch
                {
                    16 => "m4a1",
                    60 => "m4a1_silencer",

                    61 => "usp_silencer",
                    32 => "hkp2000",

                    23 => "mp5sd",
                    63 => "cz75a",
                    28 => "negev",

                    _  => weapon
                };
            }
        }

        if (weapon == "usp_s" || weapon == "usp") return "usp_silencer";
        if (weapon == "p2000") return "hkp2000";
        if (weapon == "m4a4") return "m4a1";

        return string.IsNullOrEmpty(weapon) ? "unknown" : weapon;
    }   

    private HookResult OnItemEquip(EventItemEquip @event, GameEventInfo info)
    {
        if (_isWarmup || @event.Userid == null || !@event.Userid.IsValid) return HookResult.Continue;
        string weapon = GetVerifiedWeaponName(@event.Userid, @event.Item);
        _lastActiveWeapon[@event.Userid.Slot] = weapon;
        return HookResult.Continue;
    }

    private HookResult OnWeaponFire(EventWeaponFire @event, GameEventInfo info)
    {
        if (_isWarmup || @event.Userid == null || !@event.Userid.IsValid) return HookResult.Continue;

        string weapon = GetVerifiedWeaponName(@event.Userid, @event.Weapon);
        _lastActiveWeapon[@event.Userid.Slot] = weapon;
        if (!IsIgnoredForShots(weapon))
        {
            GetWeaponStatsSafe(@event.Userid.Slot, weapon).Shots++;
        }
        return HookResult.Continue;
    }

    private HookResult OnPlayerHurt(EventPlayerHurt @event, GameEventInfo info)
    {
        if (_isWarmup || @event.Attacker == null || @event.Userid == null) return HookResult.Continue;
    
        var attacker = @event.Attacker;
        var victim = @event.Userid;

        if (attacker.IsValid && attacker.Slot != victim.Slot)
        {
            string weapon = GetVerifiedWeaponName(attacker, @event.Weapon);
            var stats = GetWeaponStatsSafe(attacker.Slot, weapon);
        
            stats.Hits++;
            stats.Damage += @event.DmgHealth;
        
            int hGroup = @event.Hitgroup;
        
            if (hGroup < 0 || hGroup > 8) 
            {
                hGroup = 0;
            }
        
            stats.HitGroups[hGroup]++;
        }
        return HookResult.Continue;
    }

    private HookResult OnPlayerDeath(EventPlayerDeath @event, GameEventInfo info)
    {
        if (_isWarmup || @event.Userid == null) return HookResult.Continue;

        var attacker = @event.Attacker;
        var victim = @event.Userid;
        string weapon = GetVerifiedWeaponName(attacker, @event.Weapon);

        if (attacker != null && attacker.IsValid && attacker.Slot != victim.Slot)
        {
            var stats = GetWeaponStatsSafe(attacker.Slot, weapon);
            stats.Kills++;
            if (@event.Headshot) stats.Headshots++;
            
            DumpPlayerStats(attacker);
        }
        string victimWeapon = GetPlayerActiveWeapon(victim);
        GetWeaponStatsSafe(victim.Slot, victimWeapon).Deaths++;
        DumpPlayerStats(victim);

        return HookResult.Continue;
    }

    private HookResult OnPlayerDisconnect(EventPlayerDisconnect @event, GameEventInfo info)
    {
        if (@event.Userid != null && @event.Userid.IsValid)
        {
            DumpPlayerStats(@event.Userid);
            _playerStats.Remove(@event.Userid.Slot);
            _lastActiveWeapon.Remove(@event.Userid.Slot);
        }
        return HookResult.Continue;
    }

    private HookResult OnPlayerConnectFull(EventPlayerConnectFull @event, GameEventInfo info)
    {
        if (@event.Userid != null && @event.Userid.IsValid)
        {
            InitPlayerStats(@event.Userid.Slot);
        }
        return HookResult.Continue;
    }

    private bool IsWarmup()
    {
        var gr = Utilities.FindAllEntitiesByDesignerName<CCSGameRulesProxy>("cs_gamerules").FirstOrDefault()?.GameRules;
        if (gr != null) return gr.WarmupPeriod;
        var wt = ConVar.Find("mp_warmuptime")?.GetPrimitiveValue<float>() ?? 0f;
        var dw = ConVar.Find("mp_do_warmup_period")?.GetPrimitiveValue<bool>() ?? false;
        return dw && wt > 0;
    }

    private HookResult OnPlayerTeam(EventPlayerTeam @event, GameEventInfo info)
    {
        if (@event.Userid == null || !@event.Userid.IsValid || @event.Disconnect)
        {
            return HookResult.Continue;
        }

        string teamName = @event.Team switch
        {
            2 => "TERRORIST",
            3 => "CT",
            1 => "Spectator",
            _ => ""
        };

        if (!string.IsNullOrEmpty(teamName))
        {
            string? playerLog = GetPlayerLogString(@event.Userid);
            if (playerLog != null)
            {
                LogToUDP($"\"{playerLog}\" joined team \"{teamName}\"");
            }
        }

        return HookResult.Continue;
    }

    private HookResult OnRoundStart(EventRoundStart @event, GameEventInfo info)
    {
        CheckWarmupStatus();
        return HookResult.Continue;
    }

    private void DumpPlayerStats(CCSPlayerController player)
    {
        if (player == null || !_playerStats.TryGetValue(player.Slot, out var weaponData)) return;
        string? playerLog = GetPlayerLogString(player);
        if (playerLog == null)
        {
            weaponData.Clear();
            return;
        }
        foreach (var kvp in weaponData)
        {
            if (kvp.Value.IsEmpty()) continue;
        
            if (kvp.Value.Shots == 0 && kvp.Value.Hits > 0) kvp.Value.Shots = kvp.Value.Hits;

            string msg = $"\"{playerLog}\" triggered \"weapon_stats\" (weapon \"{kvp.Key}\") (shots \"{kvp.Value.Shots}\") (hits \"{kvp.Value.Hits}\") (kills \"{kvp.Value.Kills}\") (headshots \"{kvp.Value.Headshots}\") (damage \"{kvp.Value.Damage}\") (deaths \"{kvp.Value.Deaths}\") (head \"{kvp.Value.HitGroups[1]}\") (neck \"{kvp.Value.HitGroups[8]}\") (chest \"{kvp.Value.HitGroups[2]}\") (stomach \"{kvp.Value.HitGroups[3]}\") (leftarm \"{kvp.Value.HitGroups[4]}\") (rightarm \"{kvp.Value.HitGroups[5]}\") (leftleg \"{kvp.Value.HitGroups[6]}\") (rightleg \"{kvp.Value.HitGroups[7]}\") (generic \"{kvp.Value.HitGroups[0]}\")";
        
            LogToUDP(msg);
        }
        weaponData.Clear();
    }

    private void LogToUDP(string msg, bool force = false)
    {
        if (!Config.Enable || (_isWarmup && !force) || _udpClient == null || _remoteEndPoint == null) return;
        
        try
        {
            string timestamp = DateTime.Now.ToString("MM/dd/yyyy - HH:mm:ss");
            string packet;
            if (Config.UseProxyForwarding)
            {
                packet =
                    $"PROXY Key={Config.ProxyKey} " +
                    $"{Config.GameServerIP}:{Config.GameServerPort}PROXY " +
                    $"L {timestamp}: {msg}\n";
            }
            else
            {
                packet =
                    $"L {timestamp}: {msg}\n";
            }
            byte[] data = Encoding.UTF8.GetBytes(packet);
            _udpClient.Send(data, data.Length, _remoteEndPoint);
        }
        catch (Exception ex)
        {
            Console.WriteLine($"[SuperLogs] UDP Send Error: {ex.Message}");
            if ((DateTime.Now - _lastNetworkRetry).TotalSeconds > 30)
            {
                _lastNetworkRetry = DateTime.Now;
                InitNetwork();
            }
        }
    }

    private void CheckWarmupStatus()
    {
        _isWarmup = IsWarmup();
    }

    private void InitPlayerStats(int slot)
    {
        _playerStats[slot] = new Dictionary<string, WeaponStats>();
    }

    private WeaponStats GetWeaponStatsSafe(int slot, string weapon)
    {
        if (!_playerStats.ContainsKey(slot)) InitPlayerStats(slot);
        if (!_playerStats[slot].ContainsKey(weapon)) _playerStats[slot][weapon] = new WeaponStats();
        return _playerStats[slot][weapon];
    }

    private string GetPlayerActiveWeapon(CCSPlayerController? player)
    {
        if (player == null || !player.IsValid) return "unknown";
        if (_lastActiveWeapon.TryGetValue(player.Slot, out var trackedWeapon))
        {
            return trackedWeapon;
        }
        if (player.PlayerPawn?.Value != null)
        {
            var activeWeapon = player.PlayerPawn.Value.WeaponServices?.ActiveWeapon.Value;
            if (activeWeapon != null && activeWeapon.IsValid)
            {
                string designerName = activeWeapon.DesignerName ?? "";
                if (!string.IsNullOrEmpty(designerName))
                {
                    return GetVerifiedWeaponName(player, designerName);
                }
            }
        }
        return "unknown";
    }

    private string? GetPlayerLogString(CCSPlayerController p)
    {
        if (p == null || !p.IsValid || string.IsNullOrEmpty(p.PlayerName))
        {
            return null;
        }
        string steamId = (p.AuthorizedSteamID != null) ? p.AuthorizedSteamID.SteamId2 : "BOT";
        string team = p.TeamNum switch { 2 => "TERRORIST", 3 => "CT", _ => "Unassigned" };
        string name = p.PlayerName
            .Replace("\\", "")
            .Replace("\"", "");

        int userId = p.UserId ?? p.Slot;

        return $"{name}<{userId}><{steamId}><{team}>";
    }

    private bool IsIgnoredForShots(string w) => 
        w == "flashbang" || w == "decoy" || w == "smokegrenade" || w == "c4" || w == "inferno" || w == "firebomb";

    private class WeaponStats
    {
        public int Shots = 0, Hits = 0, Damage = 0, Kills = 0, Deaths = 0, Headshots = 0;
        public int[] HitGroups = new int[9];
        public bool IsEmpty() => Shots == 0 && Hits == 0 && Damage == 0 && Deaths == 0 && Kills == 0;
    }
}