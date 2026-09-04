package TRcon;
#
# TRcon Perl Module - execute commands on a remote Half-Life2 / Source / Source 2 server using remote console.
#
# HLstatsX Community Edition - Real-time player and clan rankings and statistics
# Copyleft (L) 2008-20XX Nicholas Hastings (nshastings@gmail.com)
# http://www.hlxcommunity.com
#
# HLstatsX Community Edition is a continuation of
# ELstatsNEO - Real-time player and clan rankings and statistics
# Copyleft (L) 2008-20XX Malte Bayer (steam@neo-soft.org)
# http://ovrsized.neo-soft.org/
#
# ELstatsNEO is an very improved & enhanced - so called Ultra-Humongus Edition of HLstatsX
# HLstatsX - Real-time player and clan rankings and statistics for Half-Life 2
# http://www.hlstatsx.com/
# Copyright (C) 2005-2007 Tobias Oetzel (Tobi@hlstatsx.com)
#
# HLstatsX is an enhanced version of HLstats made by Simon Garner
# HLstats - Real-time player and clan rankings and statistics for Half-Life
# http://sourceforge.net/projects/hlstats/
# Copyright (C) 2001  Simon Garner
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
# For support and installation notes visit http://www.hlxcommunity.com

use strict;
no strict 'vars';

use Sys::Hostname;
use IO::Socket;
use IO::Select;
use bytes;
use Scalar::Util;

do "$::opt_libdir/HLstats_GameConstants.plib";

my $VERSION = "1.00";
my $TIMEOUT = 1.0;

my $SERVERDATA_EXECCOMMAND        = 2;
my $SERVERDATA_AUTH               = 3;
my $SERVERDATA_RESPONSE_VALUE     = 0;
my $SERVERDATA_AUTH_RESPONSE      = 2;
my $REFRESH_SOCKET_COUNTER_LIMIT  = 100;
my $AUTH_PACKET_ID                = 1;
my $SPLIT_END_PACKET_ID           = 2;

#
# Constructor
#

sub new
{
  my ($class_name, $server_object) = @_;
  my ($self) = {};
  bless($self, $class_name);

  $self->{"rcon_socket"}            = 0;
  $self->{"server_object"}          = $server_object;
  Scalar::Util::weaken($self->{"server_object"});
  $self->{"auth"}                   = 0;
  $self->{"refresh_socket_counter"} = 0;
  $self->{"packet_id"}              = 10;
  return $self;
}

sub execute
{
  my ($self, $command, $splitted_answer) = @_;
  if ($::g_stdin == 0) {
    my $answer = $self->sendrecv($command, $splitted_answer);
    if ($answer =~ /bad rcon_password/i) {
      &::printEvent("TRCON", "Bad Password");
    }
    return $answer;
  }
}

sub get_auth_code
{
  my ($self, $id) = @_;
  my $auth = 0;

  if ($id == $AUTH_PACKET_ID) {
    &::printEvent("TRCON", "Rcon password accepted");
    $auth = 1;
    $self->{"auth"} = 1;
  } elsif ($id == -1) {
    &::printEvent("TRCON", "Rcon password refused");
    $self->{"auth"} = 0;
    $auth           = 0;
  } else {
    &::printEvent("TRCON", "Bad password response id=$id");
    $self->{"auth"} = 0;
    $auth           = 0;
  }
  return $auth;
}

sub sendrecv
{
  my ($self, $msg, $splitted_answer) = @_;

  my $rs_counter = $self->{"refresh_socket_counter"};
  if ($rs_counter % $REFRESH_SOCKET_COUNTER_LIMIT == 0)  {
    if ($self->{"rcon_socket"} > 0) {
      shutdown($self->{"rcon_socket"}, 2);
      $self->{"rcon_socket"} = 0;
    }
    my $server_object = $self->{"server_object"};
    $self->{"rcon_socket"} = IO::Socket::INET->new(
                                                Proto    => "tcp",
                                                PeerAddr => $server_object->{address},
                                                PeerPort => $server_object->{port},
                                                Timeout  => 3,
                                );
    if (!$self->{"rcon_socket"}) {
      &::printEvent("TRCON", "Cannot setup TCP socket on " . $server_object->{address} . ":" . $server_object->{port} . ": $!");
    }
    $self->{"refresh_socket_counter"} = 0;
    $self->{"auth"} = 0;
  }

  my $r_socket  = $self->{"rcon_socket"};
  my $server    = $self->{"server_object"};

  my $auth      = $self->{"auth"};
  my $response  = "";
  my $packet_id = $self->{"packet_id"};

  if (($r_socket) && ($r_socket->connected())) {
    if ($auth == 0)  {
      &::printEvent("TRCON", "Trying to get rcon access (auth)");
      if ($self->send_rcon($AUTH_PACKET_ID, $SERVERDATA_AUTH, $server->{rcon}, "")) {
        &::printEvent("TRCON", "Couldn't send password");
        return;
      }
      my ($id, $command, $response) = $self->recieve_rcon($AUTH_PACKET_ID);
      if ($command == $SERVERDATA_AUTH_RESPONSE) {
        $auth = $self->get_auth_code($id);
      } elsif (($command == $SERVERDATA_RESPONSE_VALUE) && ($id == $AUTH_PACKET_ID)) {
         # Source servers send one junk packet during the authentication step before responding correctly
         &::printEvent("TRCON", "Junk packet from Source Engine");
         my ($id, $command, $response) = $self->recieve_rcon($AUTH_PACKET_ID);
         $auth = $self->get_auth_code($id);
      }
    }

    if ($auth == 1)  {
      $self->{"refresh_socket_counter"}++;
      $self->send_rcon($packet_id, $SERVERDATA_EXECCOMMAND, $msg);
      if ($splitted_answer > 0) {
        $self->send_rcon($SPLIT_END_PACKET_ID, $SERVERDATA_EXECCOMMAND, "");
      }
      my ($id, $command, $response) = $self->recieve_rcon($packet_id, $splitted_answer);
      $self->{"packet_id"}++;
      if ($self->{"packet_id"} > 32767) {
        $self->{"packet_id"} = 10;
      }
      return $response;
    }
  } else {
    $self->{"refresh_socket_counter"} = 0;
  }
  return;
}

#
# Send a package
#
sub send_rcon
{
  my ($self, $id, $command, $string1, $string2) = @_;
  my $data = pack("VVZ*Z*", $id, $command, $string1, $string2);
  my $size = length($data);
  if ($size > 4096) {
    &::printEvent("TRCON", "Command too long to send!");
    return 1;
  }
  $data = pack("V", $size) . $data;

  my $r_socket = $self->{"rcon_socket"};
  if ($r_socket && $r_socket->connected() && $r_socket->peeraddr()) {
    $r_socket->send($data, 0);
    return 0;
  } else {
    $self->{"refresh_socket_counter"} = 0;
  }
  return 1;
}

#
# Receive a package
#
sub recieve_rcon
{
  my ($self, $packet_id, $splitted_answer) = @_;
  my ($size, $id, $command, $msg);
  my $tmp = "";

  my $r_socket  = $self->{"rcon_socket"};
  my $server    = $self->{"server_object"};
  my $auth      = $self->{"auth"};

  if (($r_socket) && ($r_socket->connected())) {
    if (IO::Select->new($r_socket)->can_read($TIMEOUT)) {
      $r_socket->recv($tmp, 1500);
      $size = unpack("V", substr($tmp, 0, 4));
      if (!defined($size) || $size == 0) {
        $self->{"refresh_socket_counter"} = 0;
        return (-1, -1, -1);
      }
      $id      = unpack("V", substr($tmp, 4, 4));
      $command = unpack("V", substr($tmp, 8, 4));
      if ($id == $packet_id)  {
        $tmp = substr($tmp, 12, length($tmp) - 12);
        if ($splitted_answer > 0) {
          my $last_packet_id = $id;
          while ($last_packet_id != $SPLIT_END_PACKET_ID) {
            if (IO::Select->new($r_socket)->can_read($TIMEOUT)) {
              my $split_data = "";
              $r_socket->recv($split_data, 1500);
              my $split_size    = unpack("V", substr($split_data, 0, 4));
              my $split_id      = unpack("V", substr($split_data, 4, 4));
              my $split_command = unpack("V", substr($split_data, 8, 4));
              if (defined($split_id) && $split_id == $last_packet_id) {
                $split_data = substr($split_data, 12, length($split_data) - 12);
                $tmp .= $split_data;
              }
              if (!defined($split_id) || $split_id == $SPLIT_END_PACKET_ID) {
                $last_packet_id = $SPLIT_END_PACKET_ID;
              } else {
                $last_packet_id = $split_id;
              }
            } else {
              &::printNotice("TRCON", "Multiple packet error");
              $last_packet_id = $SPLIT_END_PACKET_ID;
            }
          }
        }
        if (length($tmp) > 0)  {
          $tmp .= "\x00";
          my ($string1, $string2) = unpack("Z*Z*", $tmp);
          $msg = $string1 . $string2;
        } else {
          $msg = "";
        }
      }
      return ($id, $command, $msg);
    } else {
      $self->{"refresh_socket_counter"} = 0;
      return (-1, -1, -1);
    }
  } else {
    $self->{"refresh_socket_counter"} = 0;
    return (-1, -1, -1);
  }
}

# Alias for legacy compatibility
sub receive_rcon { my $self = shift; return $self->recieve_rcon(@_); }

#
# Get error message
#
sub error
{
  my ($self) = @_;
  return $self->{"rcon_error"};
}

#
# Parse status command output into player information
#
sub getPlayers
{
  my ($self) = @_;
  my $status = $self->execute("status", 1);
  if (!$status)
  {
    return ();
  }

  my @lines = split(/[\r\n]+/, $status);
  my %players;

  foreach my $line (@lines)
  {
    # Clean line: strip leading engine prefixes and whitespace
    $line =~ s/^\s*(?:\[(?:Client|Server|EngineServiceManager)\]\s*)?//i;
    $line =~ s/^\s+//;
    $line =~ s/\s+$//;

    # Skip all headers and CS2 65535 ghost sockets
    next if ($line eq "" || $line =~ /^(?:server\s*:|client\s*:|-----|@\s*current|source\s*:|hostname\s*:|spawn\s*:|version\s*:|steamid\s*:|udp\/ip\s*:|os\/type\s*:|players\s*:|---------|loaded\s+spawngroup|id\s+time|#\s*userid|#end)/i);
    next if ($line =~ /^\s*65535\b/);

    my $match = 0;
    my $name     = "";
    my $userid   = "";
    my $uniqueid = "";
    my $time     = "00:00";
    my $ping     = 0;
    my $loss     = 0;
    my $state    = "active";
    my $address  = "";
    my $port     = 0;

    # 1. Counter-Strike 2 (Source 2 Engine - Humans)
    # Handles: 0unknown, 786432unknown, H:MM:SS time, 'Name' with single quotes
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
    # 2. Counter-Strike 2 (Source 2 Engine - Bots)
    # Format: 1      BOT    0    0     active      0 BotName (or # 1 ...)
    elsif ($line =~ /^\s*#?\s*(\d+)\s+BOT\s+(\d+)\s+(\d+)\s+(\S+)\s+\d+\s+[\x22\x27]?(.*?)[\x22\x27]?$/)
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
      $name     =~ s/^[\x22\x27]+|[\x22\x27]+$//g;
      $uniqueid = "BOT";
    }
    # 3. Source 1 Engine (TF2, CS:GO, CS:S, L4D, L4D2, DoD:S, etc.)
    # Formats: # 10 1 Player STEAM_1:0:12345 05:20 45 0 active 192.168.1.50:27005
    elsif ($line =~ /^\#\s*(\d+)\s+(?:\d+\s+)?[\x22\x27]?(.+?)[\x22\x27]?\s+(\[U:\d+:\d+\]|STEAM_[A-Z0-9_:]+|VALVE_[A-Z0-9_:]+|UNKNOWN|BOT)\s+([\d:]+)\s+(\d+)\s+(\d+)\s+(\S+)\s+([^:\s]+):(\S+)$/)
    {
      $match    = 1;
      $userid   = $1;
      $name     = $2;
      $name     =~ s/^[\x22\x27]+|[\x22\x27]+$//g;
      $uniqueid = $3;
      $time     = $4;
      $ping     = int($5);
      $loss     = int($6);
      $state    = $7;
      $address  = $8;
      $port     = $9;
      $uniqueid =~ s!\[U:1:(\d+)\]!(($1 % 2) . ":" . int($1 / 2))!eg;
      $uniqueid =~ s/^STEAM_[0-9]+?://i;
    }
    # 4. Source 1 Engine (Bots without IP or Connecting players)
    # Format: # 12 BotName BOT active
    elsif ($line =~ /^\#\s*(\d+)\s+(?:\d+\s+)?[\x22\x27]?(.+?)[\x22\x27]?\s+(BOT|UNKNOWN)\s+(\S+)/)
    {
      $match    = 1;
      $userid   = $1;
      $name     = $2;
      $name     =~ s/^[\x22\x27]+|[\x22\x27]+$//g;
      $uniqueid = "BOT";
      $time     = "00:00";
      $ping     = 0;
      $loss     = 0;
      $state    = $4;
      $address  = "127.0.0.1";
      $port     = 0;
    }
    # 5. HL1 / GoldSrc fallback
    # Format: 1 PlayerName 1 STEAM_0:1:4153990 0 00:33 13 0 192.168.5.115:27005
    elsif ($line =~ /^\#?\s*\d+\s+[\x22\x27]?(.+?)[\x22\x27]?\s+(\d+)\s+([^\s]+)\s+[-+]?\d+\s+([\d:]+)\s+(\d+)\s+(\d+)\s+([^:\s]+):(\S+)$/)
    {
      $match    = 1;
      $name     = $1;
      $name     =~ s/^[\x22\x27]+|[\x22\x27]+$//g;
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
  my $status = $self->execute("status", 1);

  my $server_object = $self->{server_object};
  my $game = $server_object->{play_game};

  my @lines = split(/[\r\n]+/, $status);

  my $servhostname = "";
  my $map          = "";
  my $max_players  = 0;
  my $difficulty   = 0;

  foreach my $line (@lines)
  {
    if ($line =~ /^\s*hostname\s*:\s*([\S].*)$/)
    {
      $servhostname = $1;
      $servhostname =~ s/^\s+|\s+$//g;
    }
    elsif ($line =~ /^\s*map\s*:\s*([\S]+).*$/)
    {
      $map = $1;
      $map =~ s/\.bsp$//i;
    }
    elsif ($line =~ /^Game Time\s*(\d*?:?\d+:\d+),\s*Mod\s*[\x22\x27]([^\x22\x27]+)[\x22\x27],\s*Map\s*[\x22\x27]([^\x22\x27]+)[\x22\x27]\s*$/)
    {
      $map = $3;
      $map =~ s/\.bsp$//i;
    }
    elsif ($line =~ /loaded spawngroup\(\s*\d+\)\s*:\s*SV:\s*\[\d+:\s*(?:workshop\/[^\/]+\/)?([a-zA-Z0-9_\-]+)(?:\s*\|\s*main lump|\s*\|\s*mapload)/i)
    {
      my $candidate = $1;
      unless ($candidate =~ /^(?:prefabs|maps\/prefabs|team_select|end_of_match|counterterrorist|terrorist)/i) {
        $map = $candidate if ($map eq "");
      }
    }
    elsif ($line =~ /^\s*players\s*:\s*\d+[^(]+\((\d+)\/?\d?\s*max.*$/)
    {
      $max_players = int($1) if (int($1) > 0);
    }
  }
  if (defined($game) && $game == L4D()) {
    $difficulty = $self->getDifficulty();
  }
  return ($servhostname, $map, $max_players, $difficulty);
}

sub getVisiblePlayers
{
  my ($self) = @_;
  my $status = $self->execute("sv_visiblemaxplayers");

  my @lines = split(/[\r\n]+/, $status);

  my $max_players = -1;
  foreach my $line (@lines)
  {
    # sv_visiblemaxplayers
    # Overrides the max players reported to prospective clients
    if ($line =~ /^\s*[\x22\x27]sv_visiblemaxplayers[\x22\x27]\s*=\s*[\x22\x27]([-0-9]+)[\x22\x27].*$/)
    {
      $max_players = int($1);
    }
  }
  return ($max_players);
}

my %l4d_difficulties = (
  'Easy'       => 1,
  'Normal'     => 2,
  'Hard'       => 3,
  'Impossible' => 4
);

sub getDifficulty
{
  # z_difficulty
  # Difficulty of the current game (Easy, Normal, Hard, Impossible)

  my ($self) = @_;
  my $zdifficulty = $self->execute("z_difficulty");

  my @lines = split(/[\r\n]+/, $zdifficulty);

  foreach my $line (@lines)
  {
    if ($line =~ /^\s*[\x22\x27]z_difficulty[\x22\x27]\s*=\s*[\x22\x27]([A-Za-z]+)[\x22\x27].*$/)
    {
      if (exists($l4d_difficulties{$1}))
      {
        return $l4d_difficulties{$1};
      }
    }
  }
  return 0;
}

#
# Get information about a player by userID or uniqueID
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
    $self->{"rcon_error"} = "No such player # $uniqueid";
    return 0;
  }
}

1;
# end