using CounterStrikeSharp.API;
using CounterStrikeSharp.API.Core;
using CounterStrikeSharp.API.Core.Attributes;
using CounterStrikeSharp.API.Modules.Utils;
using CounterStrikeSharp.API.Modules.Entities;
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
        public int Port { get; set; } = 26999; //Port of the HLStatsX Daemon or UDP Forwarder

        [JsonPropertyName("Enable_Logging")]
        public bool Enable { get; set; } = true; //Enable UDP logging
}

[MinimumApiVersion(80)]
public class HLStatsX_SuperLogs : BasePlugin, IPluginConfig<HLStatsXConfig>
{
    public override string ModuleName => "HLStatsX:CE SuperLogs CS2";
    public override string ModuleVersion => "2.1";
    public override string ModuleAuthor => "lovasatt";

    public HLStatsXConfig Config { get; set; } = new HLStatsXConfig();
    
    private readonly Dictionary<int, Dictionary<string, WeaponStats>> _playerStats = new();
    private UdpClient? _udpClient;
    private IPEndPoint? _remoteEndPoint;
    private bool _isWarmup = false;

    public void OnConfigParsed(HLStatsXConfig config)
    {
        this.Config = config;
        InitNetwork();
    }

    public override void Load(bool hotReload)
    {
        RegisterEventHandler<EventPlayerHurt>(OnPlayerHurt);
        RegisterEventHandler<EventPlayerDeath>(OnPlayerDeath);
        RegisterEventHandler<EventWeaponFire>(OnWeaponFire);
        RegisterEventHandler<EventRoundStart>(OnRoundStart);
        RegisterEventHandler<EventPlayerDisconnect>(OnPlayerDisconnect);
        RegisterEventHandler<EventWarmupEnd>((@e, @i) => 
        { 
            _isWarmup = false; 
            LogToUDP($"Started map \"{Server.MapName}\"", true); 
            return HookResult.Continue; 
        });

        InitNetwork();

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
            if (IPAddress.TryParse(Config.Host, out var ip))
            {
                _remoteEndPoint = new IPEndPoint(ip, Config.Port);
            }
        }
        catch (Exception ex)
        {
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

        if (player != null && player.IsValid && player.PlayerPawn.Value != null)
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
                    519 => "negev",

                    _  => weapon
                };
            }
        }

        if (weapon == "usp_s" || weapon == "usp") return "usp_silencer";
        if (weapon == "p2000") return "hkp2000";
        if (weapon == "m4a4") return "m4a1";

        return string.IsNullOrEmpty(weapon) ? "unknown" : weapon;
    }   

    private HookResult OnWeaponFire(EventWeaponFire @event, GameEventInfo info)
    {
        if (_isWarmup || @event.Userid == null || !@event.Userid.IsValid) return HookResult.Continue;

        string weapon = GetVerifiedWeaponName(@event.Userid, @event.Weapon);
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
        
            if (hGroup <= 0 || hGroup > 7) 
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

        GetWeaponStatsSafe(victim.Slot, weapon).Deaths++;
        DumpPlayerStats(victim);

        return HookResult.Continue;
    }

    private HookResult OnPlayerDisconnect(EventPlayerDisconnect @event, GameEventInfo info)
    {
        if (@event.Userid != null)
        {
            _playerStats.Remove(@event.Userid.Slot);
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

        foreach (var kvp in weaponData)
        {
            if (kvp.Value.IsEmpty()) continue;
        
            if (kvp.Value.Shots == 0 && kvp.Value.Hits > 0) kvp.Value.Shots = kvp.Value.Hits;

            string msg = $"\"{GetPlayerLogString(player)}\" triggered \"weapon_stats\" (weapon \"{kvp.Key}\") (shots \"{kvp.Value.Shots}\") (hits \"{kvp.Value.Hits}\") (kills \"{kvp.Value.Kills}\") (headshots \"{kvp.Value.Headshots}\") (damage \"{kvp.Value.Damage}\") (head \"{kvp.Value.HitGroups[1]}\") (chest \"{kvp.Value.HitGroups[2]}\") (stomach \"{kvp.Value.HitGroups[3]}\") (leftarm \"{kvp.Value.HitGroups[4]}\") (rightarm \"{kvp.Value.HitGroups[5]}\") (leftleg \"{kvp.Value.HitGroups[6]}\") (rightleg \"{kvp.Value.HitGroups[7]}\") (generic \"{kvp.Value.HitGroups[0]}\")";
        
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
            byte[] data = Encoding.UTF8.GetBytes($"L {timestamp}: {msg}\n");
            _udpClient.Send(data, data.Length, _remoteEndPoint);
        }
        catch (Exception ex)
	{
	    Console.WriteLine($"[SuperLogs] UDP Send Error: {ex.Message}");
	}
    }

    private void CheckWarmupStatus()
    {
        var gameRules = Utilities.FindAllEntitiesByDesignerName<CCSGameRulesProxy>("cs_gamerules").FirstOrDefault();
        _isWarmup = gameRules?.GameRules?.WarmupPeriod ?? false;
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

    private string GetPlayerLogString(CCSPlayerController p)
    {
        string steamId = (p.AuthorizedSteamID != null) ? p.AuthorizedSteamID.SteamId2 : "BOT";
        string team = p.TeamNum switch { 2 => "TERRORIST", 3 => "CT", _ => "Unassigned" };
        return $"{p.PlayerName}<{p.UserId}><{steamId}><{team}>";
    }

    private bool IsIgnoredForShots(string w) => 
        w == "flashbang" || w == "decoy" || w == "smokegrenade" || w == "c4" || w == "inferno" || w == "firebomb";

    private class WeaponStats
    {
        public int Shots = 0, Hits = 0, Damage = 0, Kills = 0, Deaths = 0, Headshots = 0;
        public int[] HitGroups = new int[8];
        public bool IsEmpty() => Shots == 0 && Hits == 0 && Damage == 0 && Deaths == 0 && Kills == 0;
    }
}