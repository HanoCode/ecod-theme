/**
 * Ecod QR — 极简二维码生成器（仅站内使用）
 *
 * 字节模式，版本 1-15（约 341 字节 @L / 271 @M，足够放文章链接），
 * 支持 L / M 两级纠错。纯原生实现，无外部依赖。
 *
 * 用法：EcodQR.matrix(text, 'M') → boolean[][]（true = 深色模块）
 * 算法遵循 ISO/IEC 18004，块结构表与对齐图案坐标引自标准附录。
 *
 * Copyright (c) 2026 Ecod · MIT License · https://ecod.cc
 */
(function (global) {
    'use strict';

    /* ---------- GF(256)，本原多项式 0x11d ---------- */
    var EXP = new Array(256);
    var LOG = new Array(256);
    (function () {
        var x = 1;
        for (var i = 0; i < 255; i++) {
            EXP[i] = x;
            LOG[x] = i;
            x <<= 1;
            if (x & 0x100) x ^= 0x11d;
        }
        EXP[255] = EXP[0];
    }());

    function gmul(a, b) {
        if (a === 0 || b === 0) return 0;
        return EXP[(LOG[a] + LOG[b]) % 255];
    }

    /* ---------- RS 分块表（v1-15，L=0 / M=1） ----------
       每项：[块数, 总码字, 数据码字, （第二组同结构）…] */
    var RS_TABLE = [
        /* L */ [1,26,19], [1,44,34], [1,70,55], [1,100,80], [1,134,108],
                [2,86,68], [2,98,78], [2,121,97], [2,146,116], [2,86,68,2,87,69],
                [4,101,81], [2,116,92,2,117,93], [4,133,107], [3,145,115,1,146,116], [5,109,87,1,110,88],
        /* M */ [1,26,16], [1,44,28], [1,70,44], [2,50,32], [2,67,43],
                [4,43,27], [4,49,31], [2,60,38,2,61,39], [3,58,36,2,59,37], [4,69,43,1,70,44],
                [1,80,50,4,81,51], [6,58,36,2,59,37], [8,59,37,1,60,38], [4,64,40,5,65,41], [5,65,41,5,66,42]
    ];
    /* 每版本总纠错码字（L / M），用于分块表换算 */
    var EC_PER_BLOCK = {
        L: [7,10,15,20,26,18,20,24,30,18,20,24,26,30,22],
        M: [10,16,26,18,24,16,18,22,22,26,30,22,22,26,26]
    };

    /* 对齐图案中心坐标（v1-15） */
    var ALIGN = [
        [], [6,18], [6,22], [6,26], [6,30], [6,34],
        [6,22,38], [6,24,42], [6,26,46], [6,28,50], [6,30,54],
        [6,32,58], [6,34,62], [6,26,46,66], [6,26,48,70]
    ];

    var LEVEL_BITS = { L: 1, M: 0 }; /* 格式信息里的纠错级别指示 */

    /* ---------- 数据编码（字节模式） ---------- */
    function buildCodewords(bytes, version, level) {
        var ecPerBlock = EC_PER_BLOCK[level][version - 1];
        var blocks = RS_TABLE[(level === 'L' ? 0 : 15) + version - 1];

        /* 位流：模式 0100 + 长度（v1-9 为 8 位，v10+ 为 16 位）+ 数据 */
        var countBits = version < 10 ? 8 : 16;
        var bits = [];
        function put(value, len) {
            for (var i = len - 1; i >= 0; i--) bits.push((value >>> i) & 1);
        }
        put(4, 4);
        put(bytes.length, countBits);
        for (var i = 0; i < bytes.length; i++) put(bytes[i], 8);

        /* 数据容量 = 总数据码字 × 8 */
        var dataTotal = 0;
        for (var b = 0; b < blocks.length; b += 3) dataTotal += blocks[b] * blocks[b + 2];

        /* 终止符 ≤4 位，补齐到字节，再补 0xEC/0x11 填满 */
        var capacity = dataTotal * 8;
        for (var t = 0; t < 4 && bits.length < capacity; t++) bits.push(0);
        while (bits.length % 8 !== 0) bits.push(0);
        var pad = [0xEC, 0x11], pi = 0;
        while (bits.length < capacity) put(pad[pi++ % 2], 8);

        /* 位流 → 字节 */
        var data = [];
        for (var d = 0; d < bits.length; d += 8) {
            var byte = 0;
            for (var j = 0; j < 8; j++) byte = (byte << 1) | bits[d + j];
            data.push(byte);
        }

        /* 按块切分，逐块计算 RS 纠错码字 */
        var dataBlocks = [], ecBlocks = [], offset = 0;
        for (var g = 0; g < blocks.length; g += 3) {
            for (var n = 0; n < blocks[g]; n++) {
                var dc = blocks[g + 2];
                var chunk = data.slice(offset, offset + dc);
                offset += dc;
                dataBlocks.push(chunk);
                ecBlocks.push(rsEncode(chunk, ecPerBlock));
            }
        }

        /* 交织：数据码字轮流取各块第 k 字节，然后纠错码字同样轮流 */
        var out = [];
        var maxDc = 0;
        for (var q = 0; q < dataBlocks.length; q++) maxDc = Math.max(maxDc, dataBlocks[q].length);
        for (var k = 0; k < maxDc; k++) {
            for (var q2 = 0; q2 < dataBlocks.length; q2++) {
                if (k < dataBlocks[q2].length) out.push(dataBlocks[q2][k]);
            }
        }
        for (var e = 0; e < ecPerBlock; e++) {
            for (var q3 = 0; q3 < ecBlocks.length; q3++) out.push(ecBlocks[q3][e]);
        }
        return out;
    }

    /* RS 编码：消息多项式乘 x^ec 后对生成多项式取模 */
    function rsEncode(data, ecLen) {
        var gen = [1];
        for (var i = 0; i < ecLen; i++) {
            /* gen *= (x - α^i) */
            var next = new Array(gen.length + 1);
            for (var j = 0; j < next.length; j++) {
                var a = j < gen.length ? gen[j] : 0;
                var b = j > 0 ? gmul(gen[j - 1], EXP[i]) : 0;
                next[j] = a ^ b;
            }
            gen = next;
        }
        var res = data.slice();
        for (var s = 0; s < ecLen; s++) res.push(0);
        for (var m = 0; m < data.length; m++) {
            var factor = res[m];
            if (factor === 0) continue;
            for (var n = 0; n < gen.length; n++) {
                res[m + n] ^= gmul(gen[n], factor);
            }
        }
        return res.slice(data.length);
    }

    /* ---------- 格式 / 版本信息 ---------- */
    function formatBits(level, mask) {
        var data = (LEVEL_BITS[level] << 3) | mask;
        /* 标准 BCH(15,5)：10 位余数按位计算 */
        var g = 0x537, rem = data << 10;
        for (var i = 14; i >= 10; i--) {
            if ((rem >>> i) & 1) rem ^= g << (i - 10);
        }
        return ((data << 10) | rem) ^ 0x5412;
    }

    function versionBits(version) {
        var rem = version << 12;
        for (var i = 17; i >= 12; i--) {
            if ((rem >>> i) & 1) rem ^= 0x1f25 << (i - 12);
        }
        return (version << 12) | rem;
    }

    function bitsLen(x) {
        var n = 0;
        while (x) { n++; x >>>= 1; }
        return n;
    }

    /* ---------- 矩阵 ---------- */
    function matrix(text, level) {
        level = level === 'L' ? 'L' : 'M';
        var bytes = toUtf8(String(text));

        /* 自动选版本（v1-15）：模式 4 位 + 长度位 + 数据位 ≤ 容量 */
        var version = 0;
        for (var v = 1; v <= 15; v++) {
            var cap = 0;
            var blocks = RS_TABLE[(level === 'L' ? 0 : 15) + v - 1];
            for (var b = 0; b < blocks.length; b += 3) cap += blocks[b] * blocks[b + 2];
            var need = 4 + (v < 10 ? 8 : 16) + bytes.length * 8;
            if (need <= cap * 8) {
                version = v;
                break;
            }
        }
        if (!version) return null;

        var size = version * 4 + 17;
        var m = [];
        var func = []; /* 功能模块标记：掩码只作用于数据区 */
        for (var r = 0; r < size; r++) {
            m.push(new Array(size).fill(null));
            func.push(new Array(size).fill(false));
        }

        placeFinder(m, func, 0, 0);
        placeFinder(m, func, size - 7, 0);
        placeFinder(m, func, 0, size - 7);

        /* 时序图案 */
        for (var t = 8; t < size - 8; t++) {
            m[6][t] = t % 2 === 0;
            m[t][6] = t % 2 === 0;
            func[6][t] = true;
            func[t][6] = true;
        }
        /* 深色模块 */
        m[size - 8][8] = true;
        func[size - 8][8] = true;

        /* 对齐图案 */
        var pos = ALIGN[version - 1];
        for (var ai = 0; ai < pos.length; ai++) {
            for (var aj = 0; aj < pos.length; aj++) {
                var cy = pos[ai], cx = pos[aj];
                if (m[cy][cx] !== null) continue; /* 与定位图案重叠（左上角）跳过 */
                placeAlign(m, func, cy, cx);
            }
        }
        /* 保留格式/版本信息区（先置 false 占位，写入数据时跳过，最后统一写） */
        reserveFormat(m, func, size, version);

        /* 数据放置：右起双列蛇形，避开第 6 列 */
        var codewords = buildCodewords(bytes, version, level);
        var bitIdx = 0, totalBits = codewords.length * 8;
        var upward = true;
        for (var col = size - 1; col > 0; col -= 2) {
            if (col === 6) col = 5;
            for (var step = 0; step < size; step++) {
                var row = upward ? size - 1 - step : step;
                for (var c = col; c >= col - 1; c--) {
                    if (m[row][c] !== null) continue;
                    var dark = false;
                    if (bitIdx < totalBits) {
                        dark = ((codewords[bitIdx >> 3] >>> (7 - (bitIdx & 7))) & 1) === 1;
                        bitIdx++;
                    }
                    m[row][c] = dark;
                }
            }
            upward = !upward;
        }

        /* 掩码：8 种全试，罚分最低者胜 */
        var best = -1, bestPenalty = Infinity, bestM = null;
        for (var mask = 0; mask < 8; mask++) {
            var copy = m.map(function (row) { return row.slice(); });
            applyMask(copy, func, mask, size);
            drawFormat(copy, level, mask, size);
            if (version >= 7) drawVersion(copy, version, size);
            var penalty = penaltyOf(copy, size);
            if (penalty < bestPenalty) {
                bestPenalty = penalty;
                best = mask;
                bestM = copy;
            }
        }

        /* 落盘选中的掩码与格式信息（bestM 已带格式位） */
        return bestM;
    }

    function toUtf8(str) {
        var out = [];
        for (var i = 0; i < str.length; i++) {
            var code = str.codePointAt(i);
            if (code > 0xffff) i++;
            if (code < 0x80) out.push(code);
            else if (code < 0x800) out.push(0xc0 | (code >> 6), 0x80 | (code & 63));
            else if (code < 0x10000) out.push(0xe0 | (code >> 12), 0x80 | ((code >> 6) & 63), 0x80 | (code & 63));
            else out.push(0xf0 | (code >> 18), 0x80 | ((code >> 12) & 63), 0x80 | ((code >> 6) & 63), 0x80 | (code & 63));
        }
        return out;
    }

    function placeFinder(m, func, row, col) {
        for (var r = -1; r <= 7; r++) {
            for (var c = -1; c <= 7; c++) {
                var y = row + r, x = col + c;
                if (y < 0 || y >= m.length || x < 0 || x >= m.length) continue;
                var edge = r === 0 || r === 6 || c === 0 || c === 6;
                var core = r >= 2 && r <= 4 && c >= 2 && c <= 4;
                if (r >= 0 && r <= 6 && c >= 0 && c <= 6) {
                    m[y][x] = edge || core;
                    func[y][x] = true;
                } else {
                    m[y][x] = false; /* 分隔符 */
                    func[y][x] = true;
                }
            }
        }
    }

    function placeAlign(m, func, row, col) {
        for (var r = -2; r <= 2; r++) {
            for (var c = -2; c <= 2; c++) {
                var edge = Math.max(Math.abs(r), Math.abs(c));
                m[row + r][col + c] = edge !== 1;
                func[row + r][col + c] = true;
            }
        }
    }

    function reserveFormat(m, func, size, version) {
        for (var i = 0; i < 9; i++) {
            if (m[8][i] === null) m[8][i] = false;
            func[8][i] = true;
            if (m[i][8] === null) m[i][8] = false;
            func[i][8] = true;
        }
        for (var j = size - 8; j < size; j++) {
            if (m[8][j] === null) m[8][j] = false;
            func[8][j] = true;
            if (m[j][8] === null) m[j][8] = false;
            func[j][8] = true;
        }
        if (version >= 7) {
            for (var k = 0; k < 6; k++) {
                for (var near = size - 11; near <= size - 9; near++) {
                    if (m[k][near] === null) m[k][near] = false;
                    func[k][near] = true;
                    if (m[near][k] === null) m[near][k] = false;
                    func[near][k] = true;
                }
            }
        }
    }

    function drawFormat(m, level, mask, size) {
        var bits = formatBits(level, mask);
        for (var i = 0; i < 15; i++) {
            var dark = ((bits >>> i) & 1) === 1;
            /* 第一份：绕左上角 */
            if (i < 6) m[i][8] = dark;
            else if (i === 6) m[7][8] = dark;
            else if (i === 7) m[8][8] = dark;
            else if (i === 8) m[8][7] = dark;
            else m[8][14 - i] = dark;
            /* 第二份：右上 + 左下 */
            if (i < 8) m[8][size - 1 - i] = dark;
            else m[size - 15 + i][8] = dark;
        }
        m[size - 8][8] = true; /* 深色模块恒为黑 */
    }

    function drawVersion(m, version, size) {
        var bits = versionBits(version);
        for (var i = 0; i < 18; i++) {
            var dark = ((bits >>> i) & 1) === 1;
            var r = Math.floor(i / 3), c = size - 11 + (i % 3);
            m[r][c] = dark;
            m[c][r] = dark;
        }
    }

    function applyMask(m, func, mask, size) {
        for (var r = 0; r < size; r++) {
            for (var c = 0; c < size; c++) {
                if (m[r][c] === null || func[r][c]) continue;
                var dark = false;
                switch (mask) {
                    case 0: dark = (r + c) % 2 === 0; break;
                    case 1: dark = r % 2 === 0; break;
                    case 2: dark = c % 3 === 0; break;
                    case 3: dark = (r + c) % 3 === 0; break;
                    case 4: dark = (Math.floor(r / 2) + Math.floor(c / 3)) % 2 === 0; break;
                    case 5: dark = (r * c) % 2 + (r * c) % 3 === 0; break;
                    case 6: dark = ((r * c) % 2 + (r * c) % 3) % 2 === 0; break;
                    case 7: dark = ((r + c) % 2 + (r * c) % 3) % 2 === 0; break;
                }
                if (dark) m[r][c] = !m[r][c];
            }
        }
    }

    /* 掩码罚分（标准 4 条规则） */
    function penaltyOf(m, size) {
        var p = 0, r, c, i;

        /* N1：同色行/列 ≥5 连（3 + 多出的长度） */
        for (r = 0; r < size; r++) {
            p += linePenalty(m[r]);
            var colv = [];
            for (c = 0; c < size; c++) colv.push(m[r][c] === null ? null : m[c][r]);
            p += linePenalty(colv);
        }
        function linePenalty(line) {
            var sum = 0, run = 1;
            for (var k = 1; k <= line.length; k++) {
                if (k < line.length && line[k] === line[k - 1] && line[k] !== null) run++;
                else { if (run >= 5) sum += 3 + (run - 5); run = 1; }
            }
            return sum;
        }

        /* N2：2×2 同色块，每个 +3 */
        for (r = 0; r < size - 1; r++) {
            for (c = 0; c < size - 1; c++) {
                var v = m[r][c];
                if (v === m[r][c + 1] && v === m[r + 1][c] && v === m[r + 1][c + 1]) p += 3;
            }
        }

        /* N3：1011101 + 4 空白的取景样式，出现一次 +40（两个方向） */
        var P1 = [true, false, true, true, true, false, true, false, false, false, false];
        var P2 = P1.slice().reverse();
        function hasPattern(line, pat) {
            for (var s = 0; s <= line.length - pat.length; s++) {
                var ok = true;
                for (var q = 0; q < pat.length; q++) {
                    if (line[s + q] !== pat[q]) { ok = false; break; }
                }
                if (ok) return true;
            }
            return false;
        }
        for (r = 0; r < size; r++) {
            var rowLine = m[r], colLine = [];
            for (c = 0; c < size; c++) colLine.push(m[c][r]);
            if (hasPattern(rowLine, P1) || hasPattern(rowLine, P2)) p += 40;
            if (hasPattern(colLine, P1) || hasPattern(colLine, P2)) p += 40;
        }

        /* N4：深色占比偏离 50%，每 5% +10 */
        var dark = 0, total = 0;
        for (r = 0; r < size; r++) for (c = 0; c < size; c++) {
            if (m[r][c] !== null) { total++; if (m[r][c]) dark++; }
        }
        p += Math.floor(Math.abs(dark * 20 - total * 10) / total) * 10;

        return p;
    }

    var api = { matrix: matrix };

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }
    global.EcodQR = api;
}(typeof window !== 'undefined' ? window : globalThis));
