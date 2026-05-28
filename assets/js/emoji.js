window.EmojiLib = (function () {
    'use strict';

    var EMOJI_MAP = {
        ':)': '🙂', ':-)': '🙂',
        ':(': '😢', ':-(': '😢',
        ':D': '😄', ':-D': '😄',
        ':P': '😛', ':-P': '😛',
        ';)': '😉', ';-)': '😉',
        ':O': '😮', ':-O': '😮',
        ':*': '😘', ':-*': '😘',
        '>:(': '😠', '>:-(': '😠',
        ":'(": '😭',
        ';/': '😕', ':-/': '😕',
        'B)': '😎', 'B-)': '😎',
        'O:)': '😇', 'O:-)': '😇',
        ':3': '🐱',
        '<3': '❤️',
        '</3': '💔',
        ':fire:': '🔥',
        ':oil:': '⛽',
        ':money:': '💰',
        ':boom:': '💥',
        ':check:': '✅',
        ':x:': '❌',
        ':star:': '⭐',
        ':lol:': '😂',
        ':cry:': '😭',
        ':up:': '👍',
        ':down:': '👎'
    };

    var EMOJIS = ['🙂', '😄', '😉', '😎', '😢', '😂', '😘', '😠', '👍', '👎', '🤝', '🔥', '⛽', '💰', '💥', '✅', '❌', '⭐', '❤️', '💬', '📈', '📉', '⚠️'];

    var _sortedKeys = Object.keys(EMOJI_MAP).sort(function (a, b) { return b.length - a.length; });

    function parseEmojis(str) {
        _sortedKeys.forEach(function (code) {
            var escaped = code.replace(/[-[\]{}()*+?.,\\^$|#\s]/g, '\\$&');
            str = str.replace(new RegExp(escaped, 'g'), EMOJI_MAP[code]);
        });
        return str;
    }

    return {
        EMOJI_MAP: EMOJI_MAP,
        EMOJIS: EMOJIS,
        parseEmojis: parseEmojis
    };
})();
