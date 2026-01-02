using CounterStrikeSharp.API;
using CounterStrikeSharp.API.Core;
using CounterStrikeSharp.API.Core.Attributes;
using CounterStrikeSharp.API.Modules.Utils;
using CounterStrikeSharp.API.Modules.Timers;
using System;
using System.Collections.Generic;
using System.Linq;
using System.Net;
using System.Net.Sockets;
using System.Text;
using System.Text.Json.Serialization;
using System.Text.RegularExpressions;

namespace HLStatsX_SuperLogs
{
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
        public override string ModuleVersion => "1.9";
        public override string ModuleAuthor => "lovasatt";

        public HLStatsXConfig Config { get; set; } = new HLStatsXConfig();
        private Dictionary<int, Dictionary<string, WeaponStats>> _playerStats = new Dictionary<int, Dictionary<string, WeaponStats>>();
        private static readonly Regex WeaponPrefixRegex = new Regex("^weapon_", RegexOptions.Compiled);
        private UdpClient? _udpClient;
        private IPEndPoint? _remoteEndPoint;
        private bool _networkInitialized = false;
        private bool _isWarmup = true;
        private bool _wasWarmup = true;

        public void OnConfigParsed(HLStatsXConfig config)
        {
            this.Config = config;
            InitNetwork();
        }

        public override void Load(bool hotReload)
        {
            RegisterListener<Listeners.OnMapStart>(OnMapStart);
            RegisterEventHandler<EventPlayerConnectFull>(OnPlayerConnectFull);
            RegisterEventHandler<EventPlayerDisconnect>(OnPlayerDisconnect);
            RegisterEventHandler<EventRoundStart>(OnRoundStart);
            RegisterEventHandler<EventRoundEnd>(OnRoundEnd);
            RegisterEventHandler<EventWarmupEnd>(OnWarmupEnd);
            RegisterEventHandler<EventWeaponFire>(OnWeaponFire);
            RegisterEventHandler<EventPlayerHurt>(OnPlayerHurt);
            RegisterEventHandler<EventPlayerDeath>(OnPlayerDeath);

            InitNetwork();
            if (hotReload) {
                foreach (var player in Utilities.GetPlayers()) if (player.IsValid) InitPlayerStats(player.Slot);
                CheckWarmupStatus();
            }
        }

        public override void Unload(bool hotReload)
        {
            try { _udpClient?.Close(); } catch { }
        }

        private void InitNetwork()
        {
            try {
                if (_udpClient != null) _udpClient.Close();
                _udpClient = new UdpClient();
                string ip = this.Config.Host.Replace("http://", "").Replace("https://", "");
                IPAddress remoteIP;
                if (!IPAddress.TryParse(ip, out remoteIP!)) {
                    var hostEntry = Dns.GetHostEntry(ip);
                    remoteIP = hostEntry.AddressList[0];
                }
                _remoteEndPoint = new IPEndPoint(remoteIP, this.Config.Port);
                _networkInitialized = true;
            } catch (Exception) { _networkInitialized = false; }
        }

        private void OnMapStart(string mapName)
        {
            _playerStats.Clear();
            _isWarmup = true;
            _wasWarmup = true;
            LogToGame($"Started map \"{mapName}\"", true);
            AddTimer(2.0f, CheckWarmupStatus);
        }

        private HookResult OnRoundStart(EventRoundStart @event, GameEventInfo info)
        {
            AddTimer(0.5f, CheckWarmupStatus);
            return HookResult.Continue;
        }

        private void CheckWarmupStatus()
        {
            var gameRulesProxy = Utilities.FindAllEntitiesByDesignerName<CCSGameRulesProxy>("cs_gamerules").FirstOrDefault();
            if (gameRulesProxy?.GameRules != null) {
                bool currentWarmup = gameRulesProxy.GameRules.WarmupPeriod;
                if (_wasWarmup && !currentWarmup) LogToGame($"Started map \"{Server.MapName}\"", true);
                _isWarmup = currentWarmup;
                _wasWarmup = currentWarmup;
            }
        }

        private HookResult OnWarmupEnd(EventWarmupEnd @event, GameEventInfo info)
        {
            _isWarmup = false;
            _wasWarmup = false;
            LogToGame($"Started map \"{Server.MapName}\"", true);
            return HookResult.Continue;
        }

        private HookResult OnPlayerConnectFull(EventPlayerConnectFull @event, GameEventInfo info) {
            if (@event.Userid != null) InitPlayerStats(@event.Userid.Slot);
            return HookResult.Continue;
        }

        private HookResult OnPlayerDisconnect(EventPlayerDisconnect @event, GameEventInfo info) {
            if (@event.Userid == null) return HookResult.Continue;
            DumpPlayerStats(@event.Userid);
            _playerStats.Remove(@event.Userid.Slot);
            return HookResult.Continue;
        }

        private HookResult OnRoundEnd(EventRoundEnd @event, GameEventInfo info) {
            foreach (var player in Utilities.GetPlayers()) if (IsValidPlayer(player)) DumpPlayerStats(player);
            return HookResult.Continue;
        }

        private HookResult OnWeaponFire(EventWeaponFire @event, GameEventInfo info) {
            if (_isWarmup || @event.Userid == null || !@event.Userid.IsValid) return HookResult.Continue;
            string weapon = SanitizeWeaponName(@event.Weapon);
            if (!IsIgnoredForShots(weapon)) GetWeaponStatsSafe(@event.Userid.Slot, weapon).Shots++;
            return HookResult.Continue;
        }

        private HookResult OnPlayerHurt(EventPlayerHurt @event, GameEventInfo info) {
            if (_isWarmup) return HookResult.Continue;
            var attacker = @event.Attacker;
            if (attacker != null && attacker.IsValid && @event.Userid != null && attacker.Slot != @event.Userid.Slot) {
                string weapon = SanitizeWeaponName(@event.Weapon);
                
                // *** CT FIREBOMB FIX ***
                if (weapon == "inferno" && attacker.TeamNum == 3) {
                    weapon = "firebomb";
                }

                var stats = GetWeaponStatsSafe(attacker.Slot, weapon);
                stats.Hits++;
                stats.Damage += @event.DmgHealth;
                if (@event.Hitgroup >= 0 && @event.Hitgroup < 8) stats.HitGroups[@event.Hitgroup]++;
            }
            return HookResult.Continue;
        }

        private HookResult OnPlayerDeath(EventPlayerDeath @event, GameEventInfo info) {
            if (_isWarmup) return HookResult.Continue;
            var victim = @event.Userid;
            var attacker = @event.Attacker;
            if (victim == null) return HookResult.Continue;

            string weapon = SanitizeWeaponName(@event.Weapon);
            if (attacker != null && attacker.IsValid && attacker.Slot != victim.Slot) {
                // *** CT FIREBOMB FIX ***
                if (weapon == "inferno" && attacker.TeamNum == 3) {
                    weapon = "firebomb";
                }
                var stats = GetWeaponStatsSafe(attacker.Slot, weapon);
                stats.Kills++;
                if (@event.Headshot) stats.Headshots++;
                
                // Send detailed StatsMe data for attacker on kill (Target section fix)
                DumpPlayerStats(attacker);
            }
            if (IsValidPlayer(victim)) GetWeaponStatsSafe(victim.Slot, (attacker != null) ? weapon : "world").Deaths++;

            // Dump victim stats (deaths)
            DumpPlayerStats(victim);

            // Note: We DO NOT send the "killed" log line here. Native engine handles it.
            return HookResult.Continue;
        }

        private void DumpPlayerStats(CCSPlayerController player) {
            if (player == null || !_playerStats.ContainsKey(player.Slot)) return;
            var playerWeapons = _playerStats[player.Slot];
            foreach (var kvp in playerWeapons) {
                if (kvp.Value.IsEmpty()) continue;
                LogToGame(string.Format("\"{0}\" triggered \"weapon_stats\" (weapon \"{1}\") (shots \"{2}\") (hits \"{3}\") (kills \"{4}\") (headshots \"{5}\") (tks \"{6}\") (damage \"{7}\") (deaths \"{8}\") (head \"{9}\") (chest \"{10}\") (stomach \"{11}\") (leftarm \"{12}\") (rightarm \"{13}\") (leftleg \"{14}\") (rightleg \"{15}\")",
                    GetPlayerLogString(player), kvp.Key, kvp.Value.Shots, kvp.Value.Hits, kvp.Value.Kills, kvp.Value.Headshots, kvp.Value.TeamKills, kvp.Value.Damage, kvp.Value.Deaths, kvp.Value.HitGroups[1], kvp.Value.HitGroups[2], kvp.Value.HitGroups[3], kvp.Value.HitGroups[4], kvp.Value.HitGroups[5], kvp.Value.HitGroups[6], kvp.Value.HitGroups[7]));
            }
            _playerStats[player.Slot].Clear();
        }

        private void InitPlayerStats(int s) { if (!_playerStats.ContainsKey(s)) _playerStats[s] = new Dictionary<string, WeaponStats>(); else _playerStats[s].Clear(); }
        private WeaponStats GetWeaponStatsSafe(int s, string w) { if (!_playerStats.ContainsKey(s)) _playerStats[s] = new Dictionary<string, WeaponStats>(); if (!_playerStats[s].ContainsKey(w)) _playerStats[s][w] = new WeaponStats(); return _playerStats[s][w]; }
        private string GetPlayerLogString(CCSPlayerController p) => $"{p.PlayerName}<{p.UserId}><{((!p.IsBot && p.AuthorizedSteamID != null) ? p.AuthorizedSteamID.SteamId2 : "BOT")}><{(p.TeamNum == 2 ? "TERRORIST" : (p.TeamNum == 3 ? "CT" : "Unassigned"))}>";
        private string SanitizeWeaponName(string w) { string c = WeaponPrefixRegex.Replace(w.ToLower(), ""); return c == "incgrenade" ? "firebomb" : (c == "molotov" ? "inferno" : c); }
        private bool IsIgnoredForShots(string w) => w.Contains("grenade") || w == "flashbang" || w == "decoy" || w == "molotov" || w == "inferno" || w == "firebomb" || w == "c4";
        private bool IsValidPlayer(CCSPlayerController? p) => p != null && p.IsValid && !p.IsHLTV;
        private class WeaponStats { public int Shots=0, Hits=0, Damage=0, Kills=0, Deaths=0, Headshots=0, TeamKills=0; public int[] HitGroups = new int[8]; public bool IsEmpty() => Shots == 0 && Hits == 0 && Damage == 0 && Kills == 0 && Deaths == 0; }

        private void LogToGame(string msg, bool force = false) 
        {
            if (this.Config.Enable && (!_isWarmup || force) && _networkInitialized && _udpClient != null && _remoteEndPoint != null) 
            {
                try 
                {
                    string timestamp = DateTime.Now.ToString("MM/dd/yyyy - HH:mm:ss");
                    string fullLogLine = $"L {timestamp}: {msg}\n";
                    _udpClient.Send(Encoding.UTF8.GetBytes(fullLogLine), Encoding.UTF8.GetByteCount(fullLogLine), _remoteEndPoint);
                } catch { }
            }
        }
    }
}