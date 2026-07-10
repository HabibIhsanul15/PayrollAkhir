/**
 * AvatarInitial – Komponen avatar dengan inisial nama.
 * Reusable di seluruh halaman yang menampilkan daftar karyawan/user.
 */
export default function AvatarInitial({ letters }) {
  return (
    <div className="w-7 h-7 rounded flex items-center justify-center text-[10px] font-semibold bg-blue-50 text-blue-600 flex-shrink-0 border border-blue-100">
      {letters}
    </div>
  );
}
