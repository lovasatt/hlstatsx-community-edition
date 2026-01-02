using CounterStrikeSharp.API;
using CounterStrikeSharp.API.Core;
using CounterStrikeSharp.API.Core.Attributes.Registration;
using CounterStrikeSharp.API.Modules.Commands;
using CounterStrikeSharp.API.Modules.Menu;
using CounterStrikeSharp.API.Modules.Utils;
using MySqlConnector;
using System;
using System.Text.Json.Serialization;
using System.Threading.Tasks;

namespace Elorank_cs2;

public class ElorankConfig : BasePluginConfig
{
    [JsonPropertyName("DatabaseHost")] public string DatabaseHost { get; set; } = "127.0.0.1";
    [JsonPropertyName("DatabasePort")] public int DatabasePort { get; set; } = 3306;
    [JsonPropertyName("DatabaseUser")] public string DatabaseUser { get; set; } = "hlxuser";
    [JsonPropertyName("DatabasePassword")] public string DatabasePassword { get; set; } = "password";
    [JsonPropertyName("DatabaseName")] public string DatabaseName { get; set; } = "hlstatsx";
}

public class Elorank_cs2 : BasePlugin, IPluginConfig<ElorankConfig>
{
    public override string ModuleName => "[CS2] Elorank for HLstatsX";
    public override string ModuleVersion => "1.1";
    public override string ModuleAuthor => "lovasatt";
    public override string ModuleDescription => "Allows players to set their Competitive rank in HLstatsX manually.";

    public ElorankConfig Config { get; set; } = new();

    public void OnConfigParsed(ElorankConfig config)
    {
        Config = config;
    }

    public override void Load(bool hotReload)
    {
        Console.WriteLine("[Elorank-cs2] Plugin loaded successfully.");
    }

    [ConsoleCommand("css_mm", "Opens the MM Rank selection menu")]
    [ConsoleCommand("mm", "Opens the MM Rank selection menu")]
    public void OnCommandMM(CCSPlayerController? player, CommandInfo info)
    {
        if (player == null || !player.IsValid || player.IsBot) return;

        var menu = new ChatMenu("Válaszd ki a Competitive Rangod:");

        menu.AddMenuOption("No Rank", (p, opt) => SetRank(p, 0, "No Rank"));
        menu.AddMenuOption("Silver I", (p, opt) => SetRank(p, 1, "Silver I"));
        menu.AddMenuOption("Silver II", (p, opt) => SetRank(p, 2, "Silver II"));
        menu.AddMenuOption("Silver III", (p, opt) => SetRank(p, 3, "Silver III"));
        menu.AddMenuOption("Silver IV", (p, opt) => SetRank(p, 4, "Silver IV"));
        menu.AddMenuOption("Silver Elite", (p, opt) => SetRank(p, 5, "Silver Elite"));
        menu.AddMenuOption("Silver Elite Master", (p, opt) => SetRank(p, 6, "Silver Elite Master"));
        menu.AddMenuOption("Gold Nova I", (p, opt) => SetRank(p, 7, "Gold Nova I"));
        menu.AddMenuOption("Gold Nova II", (p, opt) => SetRank(p, 8, "Gold Nova II"));
        menu.AddMenuOption("Gold Nova III", (p, opt) => SetRank(p, 9, "Gold Nova III"));
        menu.AddMenuOption("Gold Nova Master", (p, opt) => SetRank(p, 10, "Gold Nova Master"));
        menu.AddMenuOption("Master Guardian I", (p, opt) => SetRank(p, 11, "Master Guardian I"));
        menu.AddMenuOption("Master Guardian II", (p, opt) => SetRank(p, 12, "Master Guardian II"));
        menu.AddMenuOption("Master Guardian Elite", (p, opt) => SetRank(p, 13, "Master Guardian Elite"));
        menu.AddMenuOption("Distinguished Master Guardian", (p, opt) => SetRank(p, 14, "DMG"));
        menu.AddMenuOption("Legendary Eagle", (p, opt) => SetRank(p, 15, "Legendary Eagle"));
        menu.AddMenuOption("Legendary Eagle Master", (p, opt) => SetRank(p, 16, "LEM"));
        menu.AddMenuOption("Supreme Master First Class", (p, opt) => SetRank(p, 17, "Supreme"));
        menu.AddMenuOption("The Global Elite", (p, opt) => SetRank(p, 18, "Global Elite"));

        MenuManager.OpenChatMenu(player, menu);
    }

    private void SetRank(CCSPlayerController player, int rankId, string rankName)
    {
        if (player == null || !player.IsValid) return;

        string uniqueId = GetSteam2ID(player.SteamID);

        // Aszinkron adatbázis művelet
        Task.Run(async () =>
        {
            try
            {
                var builder = new MySqlConnectionStringBuilder
                {
                    Server = Config.DatabaseHost,
                    Port = (uint)Config.DatabasePort,
                    UserID = Config.DatabaseUser,
                    Password = Config.DatabasePassword,
                    Database = Config.DatabaseName
                };

                using var connection = new MySqlConnection(builder.ConnectionString);
                await connection.OpenAsync();

                string query = @"
                    UPDATE hlstats_Players p
                    JOIN hlstats_PlayerUniqueIds u ON p.playerId = u.playerId
                    SET p.mmrank = @Rank
                    WHERE u.uniqueId = @UniqueId AND u.game = 'cs2';
                ";

                using var cmd = new MySqlCommand(query, connection);
                cmd.Parameters.AddWithValue("@Rank", rankId);
                cmd.Parameters.AddWithValue("@UniqueId", uniqueId);

                int rowsAffected = await cmd.ExecuteNonQueryAsync();

                // Visszatérés a főszálra
                Server.NextFrame(() =>
                {
                    if (player.IsValid)
                    {
                        if (rowsAffected > 0)
                        {
                            player.PrintToChat($" {ChatColors.Green}[HLStatsX] {ChatColors.Default}Your rank is now: {ChatColors.Yellow}{rankName}");
                        }
                        else
                        {
                            player.PrintToChat($" {ChatColors.Red}[HLStatsX] {ChatColors.Default}Error: Your profile was not found in HLStatsX yet.");
                            player.PrintToChat($" {ChatColors.Default}Please wait until your stats are tracked (kill someone first).");
                        }
                    }
                });
            }
            catch (Exception ex)
            {
                Console.WriteLine($"[Elorank-cs2] Database Error: {ex.Message}");
            }
        });
    }

    private string GetSteam2ID(ulong steamId64)
    {
        if (steamId64 < 76561197960265728) return "";
        long steamId32 = (long)steamId64 - 76561197960265728;
        long y = steamId32 % 2;
        long z = (steamId32 - y) / 2;
        return $"{y}:{z}";
    }
}