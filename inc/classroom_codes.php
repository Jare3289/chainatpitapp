<?php
/**
 * รหัสห้องมัธยมรูปแบบเดียวกับ rooms.classroom_code / students.class_name หลัก ๆ คือ "101" (ม.1 ห้อง 01)
 * ข้อมูลเก่าอาจเป็น "1/1" หรือ "ม.1/1" — ใช้ฟังก์ชันนี้ให้ IN (...) / แปลง canonical ตรงกัน
 */

/**
 * @return array{0:int,1:int}|null ระดับ ม.1–6 และเลขห้อง/กลุ่มในชั้น 1–99
 */
function cnp_classroom_parse_gs(string $raw): ?array
{
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }
    if (preg_match('/^ม\.?\s*(\d+)\s*\/\s*(\d+)\s*$/u', $raw, $m)) {
        return [(int) $m[1], (int) $m[2]];
    }
    if (preg_match('/^(\d+)\s*\/\s*(\d+)$/', $raw, $m)) {
        return [(int) $m[1], (int) $m[2]];
    }
    if (preg_match('/^([1-6])(\d{2})$/', $raw, $m)) {
        return [(int) $m[1], (int) $m[2]];
    }
    return null;
}

/** คืนรหัส 3 หลัก เช่น 101 หรือ null ถ้าไม่ใช่รูปแบบมาตรฐานที่รองรับ */
function cnp_classroom_canonical_code(string $raw): ?string
{
    $gs = cnp_classroom_parse_gs($raw);
    if ($gs === null) {
        return null;
    }
    [$g, $s] = $gs;
    if ($g < 1 || $g > 6 || $s < 1 || $s > 99) {
        return null;
    }
    return $g . sprintf('%02d', $s);
}

/**
 * ค่าที่ควรใส่ใน SQL IN (...) เพื่อจับคู่กับคอลัมน์ที่อาจถูกบันทึกคนละรูปแบบ
 *
 * @return list<string>
 */
function cnp_classroom_code_variants(string $raw): array
{
    $raw = trim($raw);
    if ($raw === '') {
        return [];
    }
    $can = cnp_classroom_canonical_code($raw);
    if ($can === null) {
        return [$raw];
    }
    $g = (int) $can[0];
    $s = (int) substr($can, 1);
    $slash = $g . '/' . $s;
    $thai  = 'ม.' . $g . '/' . $s;
    $out   = [$raw, $can, $slash, $thai];
    if ($s < 10) {
        $out[] = 'ม.' . $g . '/' . sprintf('%02d', $s);
        $out[] = $g . '/' . sprintf('%02d', $s);
    }
    return array_values(array_unique(array_filter($out, static fn ($v) => $v !== '')));
}

/**
 * WHERE clause: homeroom code may be in class_name OR wrongly stored in grade_level (import swap).
 *
 * @param list<string> $variants
 * @return array{0:string,1:list<string>} [ sql, params ]
 */
function cnp_timetable_homeroom_where_sql(string $tableAlias, array $variants): array
{
    if ($variants === []) {
        return ['1=0', []];
    }
    $a  = preg_replace('/[^A-Za-z0-9_]/', '', $tableAlias) ?: 't';
    $ph = implode(',', array_fill(0, count($variants), '?'));
    $sql = "({$a}.class_name IN ({$ph}) OR {$a}.grade_level IN ({$ph}))";

    return [$sql, array_merge($variants, $variants)];
}

/**
 * Append " AND {column} IN (?,?,...) " using classroom variants ($columnSql must be a literal from code, not user input).
 */
function cnp_classroom_append_sql_in(string &$where, array &$params, string $columnSql, string $raw): void
{
    $vars = cnp_classroom_code_variants($raw);
    if ($vars === []) {
        return;
    }
    $ph = implode(',', array_fill(0, count($vars), '?'));
    $where .= " AND {$columnSql} IN ({$ph})";
    foreach ($vars as $v) {
        $params[] = $v;
    }
}
