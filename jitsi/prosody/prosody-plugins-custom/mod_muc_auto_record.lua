-- Auto-start Jibri recording when a moderator/owner joins a room.
-- Hooks muc-set-affiliation (fires after token_affiliation assigns owner)
-- rather than muc-occupant-joined (fires before affiliation is set).

local st = require "util.stanza";

module:hook("muc-set-affiliation", function(event)
    local room      = event.room;
    local affiliation = event.affiliation;

    if affiliation ~= "owner" then return; end
    if room._data.auto_record_started then return; end
    room._data.auto_record_started = true;

    module:log("info", "Auto-starting Jibri recording for room: %s", room.jid);

    local iq = st.iq({
        type = "set",
        to   = "focus.meet.jitsi",
        from = module.host,
        id   = "auto-rec-" .. tostring(math.random(10000)),
    }):tag("jibri", {
        xmlns          = "http://jitsi.org/protocol/jibri",
        action         = "start",
        recording_mode = "file",
        ["app-data"]   = '{"file_recording_metadata":{"upload_credentials":{}}}',
    }):up();

    module:send(iq);
end);

module:hook("muc-room-destroyed", function(event)
    event.room._data.auto_record_started = false;
end);
