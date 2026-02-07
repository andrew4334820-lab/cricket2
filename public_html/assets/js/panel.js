(() => {
    const config = window.PANEL_CONFIG || {};
    const els = {
        teamAName: document.getElementById('team-a-name'),
        teamAScore: document.getElementById('team-a-score'),
        teamAOvers: document.getElementById('team-a-overs'),
        teamAFlag: document.getElementById('team-a-flag'),
        teamBName: document.getElementById('team-b-name'),
        teamBScore: document.getElementById('team-b-score'),
        teamBOvers: document.getElementById('team-b-overs'),
        teamBFlag: document.getElementById('team-b-flag'),
        status: document.getElementById('match-status'),
        crr: document.getElementById('metric-crr'),
        rrr: document.getElementById('metric-rrr'),
        partnership: document.getElementById('metric-partnership'),
        required: document.getElementById('metric-required'),
        batsman1Name: document.getElementById('batsman-1-name'),
        batsman1Stats: document.getElementById('batsman-1-stats'),
        batsman2Name: document.getElementById('batsman-2-name'),
        batsman2Stats: document.getElementById('batsman-2-stats'),
        bowlerName: document.getElementById('bowler-1-name'),
        bowlerStats: document.getElementById('bowler-1-stats'),
        ballStrip: document.getElementById('ball-strip'),
    };

    let lastData = null;

    const sanitize = (value) => (value && value !== '' ? value : '');

    const setText = (el, value) => {
        if (el) {
            el.textContent = value;
        }
    };

    const renderTeam = (team, prefix) => {
        const name = sanitize(team?.name) || `Team ${prefix}`;
        const score = sanitize(team?.score) || '--';
        const overs = sanitize(team?.overs) ? `${team.overs} ov` : '--';
        setText(els[`team${prefix}Name`], name);
        setText(els[`team${prefix}Score`], score);
        setText(els[`team${prefix}Overs`], overs);
        setText(els[`team${prefix}Flag`], name.charAt(0).toUpperCase());
    };

    const renderBatsman = (batsman, index) => {
        const name = sanitize(batsman?.name) || `Batsman ${index}`;
        const runs = sanitize(batsman?.runs) || '0';
        const balls = sanitize(batsman?.balls) || '0';
        const fours = sanitize(batsman?.fours) || '0';
        const sixes = sanitize(batsman?.sixes) || '0';
        const sr = sanitize(batsman?.sr) || '0.0';
        setText(els[`batsman${index}Name`], name);
        setText(els[`batsman${index}Stats`], `${runs} (${balls}) · ${fours}x4 · ${sixes}x6 · SR ${sr}`);
    };

    const renderBowler = (bowler) => {
        const name = sanitize(bowler?.name) || 'Bowler';
        const overs = sanitize(bowler?.overs) || '0.0';
        const runs = sanitize(bowler?.runs) || '0';
        const wickets = sanitize(bowler?.wickets) || '0';
        const econ = sanitize(bowler?.economy) || '0.0';
        setText(els.bowlerName, name);
        setText(els.bowlerStats, `${overs}-${runs}-${wickets} · Econ ${econ}`);
    };

    const renderBalls = (balls = []) => {
        if (!els.ballStrip) return;
        const strip = Array.from({ length: 12 }, (_, i) => balls[i] || '');
        els.ballStrip.innerHTML = strip
            .map((ball) => {
                const token = ball ? ball.toString() : '';
                const display = token.toUpperCase();
                const value = token.toLowerCase();
                return `<div class="ball" data-value="${value}">${display || '-'}</div>`;
            })
            .join('');
    };

    const applyData = (data) => {
        if (!data) return;
        renderTeam(data.teams?.[0], 'A');
        renderTeam(data.teams?.[1], 'B');
        setText(els.status, sanitize(data.status) || 'Live');
        setText(els.crr, sanitize(data.crr) || '0.00');
        setText(els.rrr, sanitize(data.rrr) || '0.00');
        setText(els.partnership, sanitize(data.partnership) || '0 (0)');
        setText(els.required, sanitize(data.required) || 'Need 0 runs in 0 balls');
        renderBatsman(data.batsmen?.[0], 1);
        renderBatsman(data.batsmen?.[1], 2);
        renderBowler(data.bowler);
        renderBalls(data.balls || []);
    };

    const fetchData = async () => {
        if (!config.fetchUrl) return;
        try {
            const res = await fetch(`${config.fetchUrl}&t=${Date.now()}`, { cache: 'no-store' });
            if (!res.ok) throw new Error('Fetch failed');
            const data = await res.json();
            if (data && data.ok) {
                lastData = data;
                applyData(data);
            } else if (lastData) {
                applyData(lastData);
            }
        } catch (error) {
            if (lastData) {
                applyData(lastData);
            }
        }
    };

    fetchData();
    setInterval(fetchData, 1000);
})();
