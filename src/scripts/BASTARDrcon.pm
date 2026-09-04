package BASTARDrcon;
#
# BASTARDrcon Perl Module - execute commands on a remote Half-Life 1 server using Rcon.
# A merge of the KKrcon library into HLstatsX
#  Copyright (C) 2008-20XX  Nicholas Hastings (nshastings@gmail.com)

# KKrcon Perl Module - execute commands on a remote Half-Life server using Rcon.
# http://kkrcon.sourceforge.net
#
# TRcon Perl Module - execute commands on a remote Half-Life2 server using remote console.
# http://www.hlstatsx.com
#
# Copyright (C) 2000, 2001  Rod May
# Enhanced in 2005 by Tobi (Tobi@gameme.de)
#
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# as published by the Free Software Foundation; either version 2
# of the License, or (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
#

use strict;
use warnings;
use sigtrap;
use Socket;
use Sys::Hostname;
use Encode qw(decode_utf8 is_utf8);

##
## Main
##

#
# Constructor
#
sub new
{
    my ($class_name, $server_object) = @_;
    my ($self) = {};
    bless($self, $class_name);

    # Initialise properties
    $self->{server_object} = $server_object;
    $self->{rcon_password} = $server_object->{rcon}  or die("BASTARDrcon: a Password is required\n");
    $self->{server_host}   = $server_object->{address};
    $self->{server_port}   = int($server_object->{port}) or die("BASTARDrcon: invalid Port \"" . $server_object->{port} . "\"\n");

    $self->{socket} = undef;
    $self->{error} = "";

    # Set up socket parameters
    $self->{_ipaddr} = gethostbyname($self->{server_host}) or die("BASTARDrcon: could not resolve Host \"" . $self->{server_host} . "\"\n");

    return $self;
}

#
# Execute an Rcon command and return the response
#
sub execute
{
    my ($self, $command) = @_;
    my $msg;
    my $ans;

    # version x.1.0.6+ HL1 server
    $msg = "\xFF\xFF\xFF\xFFchallenge rcon\n\0";
    $ans = $self->_sendrecv($msg);

    if ($ans =~ /challenge +rcon +(\d+)/)
    {
        $msg = "\xFF\xFF\xFF\xFFrcon $1 \"" . $self->{"rcon_password"} . "\" $command\0";
        $ans = $self->_sendrecv($msg);
    }
    elsif (!$self->error())
    {
        $ans = "";
        $self->{"error"} = "No challenge response";
    }

    if ($ans =~ /bad rcon_password/i)
    {
        $self->{"error"} = "Bad Password";
    }

    # Normalize response to clean UTF-8 string
    if ($ans && !is_utf8($ans)) {
        eval { $ans = decode_utf8($ans); };
    }

    return $ans;
}

sub _sendrecv
{
    my ($self, $msg) = @_;
    my $host   = $self->{"server_host"};
    my $port   = $self->{"server_port"};
    my $ipaddr = $self->{"_ipaddr"};
    my $proto  = getprotobyname('udp') || 0;

    # Open socket
    socket($self->{"socket"}, PF_INET, SOCK_DGRAM, $proto) or die("BASTARDrcon(141): socket: $!\n");
    my $hispaddr = sockaddr_in($port, $ipaddr);

    die("BASTARDrcon: send $host:$port : $!") unless(defined(send($self->{"socket"}, $msg, 0, $hispaddr)));

    my $rin = "";
    vec($rin, fileno($self->{"socket"}), 1) = 1;
    my $ans = "TIMEOUT";
    if (select($rin, undef, undef, 0.75))
    {
        $ans = "";
        $hispaddr = recv($self->{"socket"}, $ans, 16384, 0);
        $ans =~ s/\x00+$//;                                     # trailing crap
        $ans =~ s/^\xFF\xFF\xFF\xFFl//;         # HL response
        $ans =~ s/^\xFF\xFF\xFF\xFFn//;         # QW response
        $ans =~ s/^\xFF\xFF\xFF\xFF//;          # Q2/Q3 response
        $ans =~ s/^\xFE\xFF\xFF\xFF.....//;     # old HL bug/feature
    }
    # Close socket
    close($self->{"socket"});

    if ($ans eq "TIMEOUT")
    {
        $ans = "";
        $self->{"error"} = "Rcon timeout";
    }
    return $ans;
}

#
# Send a package
#
sub send_rcon
{
    my ($self, $id, $command, $string1, $string2) = @_;
    my $tmp = pack("VVZ*Z*",$id,$command,$string1,$string2);
    my $size = length($tmp);
    if($size > 4096)
    {
        $self->{error} = "Command too long to send!";
        return 1;
    }
    $tmp = pack("V", $size) .$tmp;

    unless(defined(send($self->{"socket"},$tmp,0)))
    {
        die("BASTARDrcon: send $!");
    }
    return 0;
}

#
#  Receive a package
#
sub receive_rcon
{
    my $self = shift;
    my ($size, $id, $command, $msg);
    my $rin = "";
    my $tmp = "";

    vec($rin, fileno($self->{"socket"}), 1) = 1;
    if(select($rin, undef, undef, 0.75))
    {
        while(length($size) < 4)
        {
            $tmp = "";
            recv($self->{"socket"}, $tmp, (4-length($size)), 0);
            $size .= $tmp;
        }
        $size = unpack("V", $size);
        if($size < 10 || $size > 16384)
        {
            close($self->{"socket"});
            $self->{error} = "illegal size $size ";
            return (-1, -1, -1);
        }

        while(length($id)<4)
        {
            $tmp = "";
            recv($self->{"socket"}, $tmp, (4-length($id)), 0);
            $id .= $tmp;
        }
        $id = unpack("V", $id);
        $size = $size - 4;
        while(length($command)<4)
        {
            $tmp ="";
            recv($self->{"socket"}, $tmp, (4-length($command)),0);
            $command.=$tmp;
        }
        $command = unpack("V", $command);
        $size = $size - 4;
        my $msg = "";
        while($size >= 1)
        {
            $tmp = "";
            recv($self->{"socket"}, $tmp, $size, 0);
            $size -= length($tmp);
            $msg .= $tmp;
        }
        my ($string1,$string2) = unpack("Z*Z*",$msg);
        $msg = $string1.$string2;
        return ($id, $command, $msg);
    }
    else
    {
        return (-1, -1, -1);
    }
}

# Alias for legacy compatibility
sub recieve_rcon { my $self = shift; return $self->receive_rcon(@_); }

#
# Get error message
#
sub error
{
    my ($self) = @_;
    return $self->{"error"};
}

#
# Parse "status" command output into player information
#
sub getPlayers
{
    my ($self) = @_;
    my $status = $self->execute("status");

    return () unless ($status);

    my @lines = split(/[\r\n]+/, $status);

    my %players;

    my $match;
    my $name;
    my $userid;
    my $uniqueid;
    my $time;
    my $ping;
    my $loss;
    my $state;
    my $address;
    my $port;

    foreach my $line (@lines)
    {
        # Clean line: strip leading engine prefixes and whitespace
        $line =~ s/^\s*(?:\[(?:Client|Server|EngineServiceManager)\]\s*)?//i;
        $line =~ s/^\s+//;
        $line =~ s/\s+$//;

        # Skip all headers and metadata
        next if ($line eq "" || $line =~ /^(?:server\s*:|client\s*:|-----|@\s*current|source\s*:|hostname\s*:|spawn\s*:|version\s*:|steamid\s*:|udp\/ip\s*:|os\/type\s*:|players\s*:|---------|loaded\s+spawngroup|id\s+time|#\s*userid|#end)/i);
        next if ($line =~ /^\s*65535\b/);

        $match = 0;
        $name     = "";
        $userid   = "";
        $uniqueid = "";
        $time     = "00:00";
        $ping     = 0;
        $loss     = 0;
        $state    = "active";
        $address  = "";
        $port     = 0;

        # ---------------------------------------------------------------------
        # Game Mode: Counter-Strike 2 (Source 2 Engine - Humans)
        # Format: 0    00:09    0    0     active 786432 192.168.2.105:50231 "Player"
        # ---------------------------------------------------------------------
        if ($line =~ /^#?\s*(\d+)\s+(\S+)\s+(\d+)\s+(\d+)\s+(\S+)\s+(.*?)\s*['"](.+?)['"]\s*$/i)
        {
            $match  = 1;
            $userid = $1;
            $time   = $2;
            $ping   = int($3);
            $loss   = int($4);
            $state  = $5;
            my $middle = $6;
            $name   = $7;

            if ($middle =~ /((?:[0-9]{1,3}\.){3}[0-9]{1,3}):?(\d+)?/) {
                $address = $1;
                $port    = int($2 || 0);
            }
            $uniqueid = ($::g_mode eq "LAN") ? $address : "";
        }
        # ---------------------------------------------------------------------
        # Game Mode: Counter-Strike 2 (Source 2 Engine - Bots)
        # Format: 1      BOT    0    0     active      0 "Han"
        # ---------------------------------------------------------------------
        elsif ($line =~ /^\s*(\d+)\s+BOT\s+(\d+)\s+(\d+)\s+(\S+)\s+\d+\s+["']?(.*?)["']?$/)
        {
            $match    = 1;
            $userid   = $1;
            $time     = "00:00";
            $ping     = int($2);
            $loss     = int($3);
            $state    = $4;
            $address  = "127.0.0.1";
            $port     = 0;
            $name     = $5;
            $uniqueid = "BOT";
        }
        # ---------------------------------------------------------------------
        # Game Mode: Source 1 Engine (TF2, CS:GO, CS:S, L4D, L4D2, DoD:S, etc.)
        # Formats:
        #   # 10 1 "Player" STEAM_1:0:12345 05:20 45 0 active 192.168.1.50:27005
        #   # 10 1 "Player" [U:1:12345678]  05:20 45 0 active 192.168.1.50:27005
        #   # 10 "Player" STEAM_1:0:12345 05:20 45 0 active 192.168.1.50:27005
        # ---------------------------------------------------------------------
        elsif ($line =~ /^\#\s*(\d+)\s+(?:\d+\s+)?"(.+)"\s+(\[U:\d+:\d+\]|STEAM_[0-9:]+|VALVE_[0-9:]+|UNKNOWN|BOT)\s+([\d:]+)\s+(\d+)\s+(\d+)\s+(\S+)\s+([^:\s]+):(\S+)$/)
        {
            $match    = 1;
            $userid   = $1;
            $name     = $2;
            $uniqueid = $3;
            $time     = $4;
            $ping     = int($5);
            $loss     = int($6);
            $state    = $7;
            $address  = $8;
            $port     = $9;
            $uniqueid =~ s/^STEAM_[0-9]+?://i;
        }
        # ---------------------------------------------------------------------
        # Game Mode: Source 1 Engine (Bots without IP or Connecting players)
        # Format: # 12 "BotName" BOT active
        # ---------------------------------------------------------------------
        elsif ($line =~ /^\#\s*(\d+)\s+(?:\d+\s+)?"(.+)"\s+(BOT|UNKNOWN)\s+(\S+)/)
        {
            $match    = 1;
            $userid   = $1;
            $name     = $2;
            $uniqueid = "BOT";
            $time     = "00:00";
            $ping     = 0;
            $loss     = 0;
            $state    = $4;
            $address  = "127.0.0.1";
            $port     = 0;
        }
        # ---------------------------------------------------------------------
        # HL1 (CS 1.6, TFC, DoD, HL1, etc.)
        # Format: 1 "psychonic" 1 STEAM_0:1:4153990 0 00:33 13 0 192.168.5.115:27005
        # ---------------------------------------------------------------------
        elsif ($line =~ /^\#?\s*\d+\s+"(.+)"\s+(\d+)\s+([^\s]+)\s+[-+]?\d+\s+([\d:]+)\s+(\d+)\s+(\d+)\s+([^:\s]+):(\S+)$/)
        {
            $match    = 1;
            $name     = $1;
            $userid   = $2;
            $uniqueid = $3;
            $time     = $4;
            $ping     = int($5);
            $loss     = int($6);
            $state    = "";
            $address  = $7;
            $port     = $8;
            $uniqueid =~ s/^STEAM_[0-9]+?://i;
        }
        # ---------------------------------------------------------------------
        # HL1 - HLTV Proxy
        # Format: 1 "HLTV Proxy" 1 HLTV hltv:0/128 delay:30 00:33 10.5.0.3:27020
        # ---------------------------------------------------------------------
        elsif ($line =~ /^\#?\s*\d+\s+"([^"]+)"\s+(\d+)\s+([^\s]+)\s+(?:hltv|gotv):[^\s]+\s+delay:[^\s]+\s+([\d:]+)\s+([^:\s]+):(\S+)$/)
        {
            $match    = 1;
            $name     = $1;
            $userid   = $2;
            $uniqueid = $3;
            $time     = $4;
            $address  = $5;
            $port     = $6;
        }

        # Clean quotes and spaces from player name
        if ($name ne "") {
            $name =~ s/^[\x22\x27]+|[\x22\x27]+$//g;
            $name =~ s/^\s+|\s+$//g;
        }

        next if ($name eq "" || $name eq "''" || $name eq "\"\"");

        if ($match) {
            my $playerData = {
                "Name"       => $name,
                "UserID"     => $userid,
                "UniqueID"   => $uniqueid,
                "Time"       => $time,
                "Ping"       => $ping,
                "Loss"       => $loss,
                "State"      => $state,
                "Address"    => $address,
                "ClientPort" => $port
            };

            # Multi-Key Indexing: Store by Name, Address, UserID and UniqueID
            $players{$name}     = $playerData if ($name ne "");
            $players{$address}  = $playerData if ($address ne "");
            $players{$userid}   = $playerData if (defined($userid) && $userid ne "");
            $players{$uniqueid} = $playerData if ($uniqueid ne "" && $uniqueid ne "BOT");
        }
    }
    return %players;
}

sub getServerData
{
    my ($self) = @_;
    my $status = $self->execute("status");

    my @lines = split(/[\r\n]+/, $status);

    my $servhostname = "";
    my $map          = "";
    my $max_players  = 0;
    foreach my $line (@lines)
    {
        if ($line =~ /^\s*hostname\s*:\s*([\S].*)$/)
        {
            $servhostname   = $1;
            $servhostname   =~ s/^\s+|\s+$//g;
        }
        elsif ($line =~ /^\s*map\s*:\s*([\S]+).*$/)
        {
            $map   = $1;
            $map   =~ s/\.bsp$//i;
        }
        elsif ($line =~ /loaded spawngroup\(\s*\d+\)\s*:\s*SV:\s*\[\d+:\s*(?:workshop\/[^\/]+\/)?([a-zA-Z0-9_\-]+)(?:\s*\|\s*main lump|\s*\|\s*mapload)/i)
        {
            my $candidate = $1;
            unless ($candidate =~ /^(?:prefabs|maps\/prefabs|team_select|end_of_match|counterterrorist|terrorist)/i) {
                $map = $candidate if ($map eq "");
            }
        }
        elsif ($line =~ /^\s*players\s*:\s*\d+.+\((\d+)\s*max.*$/)
        {
            $max_players = int($1) if (int($1) > 0);
        }
    }
    return ($servhostname, $map, $max_players, 0);
}

sub getVisiblePlayers
{
    my ($self) = @_;
    my $status = $self->execute("sv_visiblemaxplayers");

    my @lines = split(/[\r\n]+/, $status);

    my $max_players = -1;
    foreach my $line (@lines)
    {
        # "sv_visiblemaxplayers" = "-1"
        #       - Overrides the max players reported to prospective clients
        if ($line =~ /^\s*"sv_visiblemaxplayers"\s*(?:=|is)\s*"([-0-9]+)".*$/)
        {
            $max_players   = int($1);
        }
    }
    return ($max_players);
}

#
# Get information about a player by userID
#

sub getPlayer
{
    my ($self, $uniqueid) = @_;
    my %players = $self->getPlayers();

    if (defined($players{$uniqueid}))
    {
        return $players{$uniqueid};
    }
    else
    {
        $self->{"error"} = "No such player # $uniqueid";
        return 0;
    }
}

1;
# end