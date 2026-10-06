<?php
// Ordinary edits do not create archives; backups are managed per semester.
function updateRegistration($conn, $id, $updates, $expectedRevision)
{
    if (!$updates) throw new Exception('ไม่มีข้อมูลสำหรับแก้ไข');
    if (!$conn->begin_transaction()) throw new Exception('เริ่มการบันทึกไม่สำเร็จ');
    try {
        $stmt = $conn->prepare('SELECT * FROM registrations WHERE id=? FOR UPDATE');
        $stmt->bind_param('i', $id);
        if (!$stmt->execute()) throw new Exception('อ่านข้อมูลผู้สมัครไม่สำเร็จ');
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) throw new Exception('ไม่พบผู้สมัคร');
        if (!hash_equals(hash('sha256', json_encode($row)), $expectedRevision)) {
            throw new Exception('ข้อมูลถูกเปลี่ยนระหว่างแก้ไข กรุณาโหลดหน้าใหม่ก่อนบันทึก');
        }
        foreach (array_keys($updates) as $key) {
            if (!array_key_exists($key, $row) || in_array($key, ['id', 'created_at', 'updated_at'], true)) {
                throw new Exception('ช่องข้อมูลไม่ถูกต้อง');
            }
        }
        $sets = array_map(fn($key) => '`' . $key . '`=?', array_keys($updates));
        $edit = $conn->prepare('UPDATE registrations SET ' . implode(',', $sets) . ' WHERE id=?');
        if (!$edit) throw new Exception('เตรียมบันทึกข้อมูลไม่สำเร็จ');
        $values = array_values($updates);
        $values[] = $id;
        $edit->bind_param(str_repeat('s', count($updates)) . 'i', ...$values);
        if (!$edit->execute()) throw new Exception('บันทึกไม่ได้ อาจมีเลขบัตรซ้ำในภาคเรียนเดียวกัน');
        if (!$conn->commit()) throw new Exception('บันทึกการทำรายการไม่สำเร็จ');
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}
