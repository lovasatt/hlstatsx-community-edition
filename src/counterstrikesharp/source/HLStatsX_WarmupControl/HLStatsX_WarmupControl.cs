using CounterStrikeSharp.API;
using CounterStrikeSharp.API.Core;
using CounterStrikeSharp.API.Core.Attributes.Registration;
using CounterStrikeSharp.API.Modules.Utils;
using CounterStrikeSharp.API.Modules.Timers;
using System.Linq;
using System;

namespace HLStatsX_WarmupControl;

public class HLStatsX_WarmupControl : BasePlugin
{
    public override string ModuleName => "HLStatsX:CE Warmup Log Control";
    public override string ModuleVersion => "1.0";
    public override string ModuleAuthor => "lovasatt";

    // Debug configuration (Set to false for production)
    private bool _debug = false;

    public override void Load(bool hotReload)
    {
        // Monitor map load
        RegisterListener<Listeners.OnMapStart>(OnMapStart);

        // Monitor round start (Runs on Warmup start AND Live match start)
        RegisterEventHandler<EventRoundStart>(OnRoundStart);

        if (_debug) Console.WriteLine("[HLStatsX-Warmup] Plugin loaded successfully.");
    }

    // --- EVENTS ---

    private void OnMapStart(string mapName)
    {
        // Wait a bit on map change, then check status
        AddTimer(1.0f, CheckStatusAndSetLog);
    }

    private HookResult OnRoundStart(EventRoundStart @event, GameEventInfo info)
    {
        // Check status at the beginning of every round (warmup or live)
        // A small delay (0.5s) is needed to ensure GameRules updates m_bWarmupPeriod
        AddTimer(0.5f, CheckStatusAndSetLog);
        return HookResult.Continue;
    }

    // --- LOGIC ---

    private void CheckStatusAndSetLog()
    {
        // Find the GameRules proxy containing Warmup info
        var gameRulesProxy = Utilities.FindAllEntitiesByDesignerName<CCSGameRulesProxy>("cs_gamerules").FirstOrDefault();

        if (gameRulesProxy != null && gameRulesProxy.GameRules != null)
        {
            // This C# property directly reads the "m_bWarmupPeriod" netprop
            bool isWarmup = gameRulesProxy.GameRules.WarmupPeriod;

            if (isWarmup)
            {
                SetLogging(false, "Warmup Active (m_bWarmupPeriod = 1)");
            }
            else
            {
                SetLogging(true, "Live Game (m_bWarmupPeriod = 0)");
            }
        }
        else
        {
            // If rules aren't found for some reason, enable logging for safety
            SetLogging(true, "Default (GameRules not found)");
        }
    }

    private void SetLogging(bool enable, string reason)
    {
        if (enable)
        {
            // Execute command to ensure state
            Server.ExecuteCommand("log on");
            if (_debug) Console.WriteLine($"[HLStatsX-Warmup] LOGGING ENABLED (log on) - Reason: {reason}");
        }
        else
        {
            Server.ExecuteCommand("log off");
            if (_debug) Console.WriteLine($"[HLStatsX-Warmup] LOGGING DISABLED (log off) - Reason: {reason}");
        }
    }
}