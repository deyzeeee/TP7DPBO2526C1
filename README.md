# TP7 DPBO — Sistem Manajemen Peminjaman Alat Laboratorium Komputer (LabGear)

Aplikasi web berbasis **PHP Native (OOP)** dengan **PDO Prepared Statement** untuk mengelola inventaris kategori alat, perangkat laboratorium, data peminjam, serta transaksi peminjaman dan pengembalian alat laboratorium secara real-time dan konsisten terhadap stok.

---

## Janji

> Saya **Muhammad Fadey Rafif** dengan NIM **2504792** mengerjakan Tugas Praktikum 7 dalam mata kuliah Desain dan Pemrograman Berorientasi Objek untuk keberkahanNya maka saya tidak melakukan kecurangan seperti yang telah dispesifikasikan. Aamiin.
---

## Tema Website & Daftar Fitur Utama

- **Tema Website**: Sistem Inventaris dan Peminjaman Alat Laboratorium Komputer (**LabGear Management System**).
- **Fitur Utama**:
  1. **CRUD Kategori Alat (`categories`)**: Mengelola kategori perangkat laboratorium beserta kode kategori, lokasi ruang lab, deskripsi, dan perhitungan otomatis jumlah jenis alat di tiap kategori.
  2. **CRUD Alat Laboratorium (`equipments`)**: Mengelola inventaris alat laboratorium yang berelasi ke tabel kategori (`category_id`), lengkap dengan kode aset unik, merek, jumlah stok tersedia, dan status kondisi alat (`Baik`, `Perlu Kalibrasi`, `Rusak Ringan`).
  3. **CRUD Data Peminjam (`borrowers`)**: Mengelola data mahasiswa/dosen/asisten peminjam beserta nomor identitas (NIM/NIP), program studi, email, nomor telepon, dan indikator jumlah pinjaman aktif.
  4. **CRUD Transaksi Peminjaman & Pengembalian (`borrowings`)**:
     - Mencatat peminjaman alat dengan relasi ke tabel `equipments` dan `borrowers`.
     - **Sinkronisasi Stok Otomatis & Transaksional**: Saat alat dipinjam, stok pada tabel `equipments` otomatis berkurang di dalam transaksi database (`beginTransaction`, `SELECT ... FOR UPDATE`, `commit`/`rollBack`).
     - **Tombol Kembalikan Cepat**: Mengubah status peminjaman dari `Dipinjam` menjadi `Dikembalikan`, mengisi tanggal kembali otomatis ke hari ini, dan mengembalikan stok alat.
     - **Proteksi Relasi & Validasi**: Menolak peminjaman jika stok tidak mencukupi dan menolak penghapusan data induk (`categories`, `equipments`, `borrowers`) yang masih digunakan oleh tabel anak (`ON DELETE RESTRICT`).

---

## Struktur Project

```text
TP7DPBO2526C1/
├── index.php                  # Titik awal aplikasi, whitelist router (?page=), & Dependency Injection
├── config/
│   ├── bootstrap.php          # Inisialisasi session, load config/helper, dan spl_autoload_register
│   ├── database.php           # Class Database untuk koneksi tunggal PDO (ERRMODE_EXCEPTION, EMULATE_PREPARES=false)
│   └── helpers.php            # Fungsi helper escape HTML e(), flash message PRG, redirect, & translasi error PDO
├── class/
│   ├── CrudRepository.php     # Interface kontrak dasar CRUD (findAll, findById, create, update, delete)
│   ├── BaseRepository.php     # Abstract class CRUD generik dengan PDO Prepared Statement & whitelist kolom
│   ├── Category.php           # Class entitas tabel categories
│   ├── Equipment.php          # Class entitas tabel equipments (+ penguncian baris & penyesuaian stok)
│   ├── Borrower.php           # Class entitas tabel borrowers
│   └── Borrowing.php          # Class entitas tabel borrowings (+ transaksi sinkronisasi stok)
├── view/
│   ├── header.php             # Elemen modular navigasi atas dan penampil flash message
│   ├── footer.php             # Elemen modular penutup halaman
│   ├── categories.php         # Halaman GUI form & tabel CRUD Kategori Alat
│   ├── equipments.php         # Halaman GUI form & tabel CRUD Alat Laboratorium
│   ├── borrowers.php          # Halaman GUI form & tabel CRUD Data Peminjam
│   └── borrowings.php         # Halaman GUI form & tabel CRUD Transaksi Peminjaman
├── style/
│   └── style.css              # Styling halaman responsif untuk form, tabel, badge, dan alert
├── database/
│   └── db_lab_gear.sql        # Struktur DDL 4 tabel (InnoDB, utf8mb4, FK) + data awal yang konsisten
├── docs/                      # Tempat menyimpan file screen record / screenshot dokumentasi
└── README.md                  # Dokumentasi lengkap proyek
```

---

## Database

### Entity-Relationship Diagram (ERD)

```mermaid
erDiagram
    categories ||--o{ equipments : "memiliki (category_id)"
    equipments ||--o{ borrowings : "dipinjam pada (equipment_id)"
    borrowers ||--o{ borrowings : "melakukan (borrower_id)"

    categories {
        int id PK
        varchar category_code UK
        varchar category_name
        varchar lab_room
        varchar description
    }

    equipments {
        int id PK
        int category_id FK
        varchar asset_code UK
        varchar equipment_name
        varchar brand
        int stock
        enum item_condition
    }

    borrowers {
        int id PK
        varchar identity_number UK
        varchar full_name
        varchar study_program
        varchar email UK
        varchar phone
    }

    borrowings {
        int id PK
        int equipment_id FK
        int borrower_id FK
        int quantity
        date borrow_date
        date return_date
        enum status
        varchar purpose
    }
```

### Penjelasan Tabel dan Foreign Key

| Nama Tabel | Primary Key | Foreign Key & Aturan Relasi | Fungsi Tabel |
| :--- | :--- | :--- | :--- |
| `categories` | `id` | - | Menyimpan data kategori peralatan dan lokasi ruang laboratorium. |
| `equipments` | `id` | `category_id` -> `categories(id)` (`ON DELETE RESTRICT ON UPDATE CASCADE`) | Menyimpan daftar inventaris alat laboratorium, merek, kondisi, dan stok saat ini. |
| `borrowers` | `id` | - | Menyimpan data anggota peminjam (mahasiswa/dosen/asisten) beserta identitas dan kontak. |
| `borrowings` | `id` | `equipment_id` -> `equipments(id)` (`ON DELETE RESTRICT ON UPDATE CASCADE`)<br>`borrower_id` -> `borrowers(id)` (`ON DELETE RESTRICT ON UPDATE CASCADE`) | Menyimpan transaksi peminjaman alat, jumlah unit dipinjam, tanggal pinjam/kembali, status, dan keperluan. |

---

## Class Diagram & Prinsip OOP (SOLID)

### Class Diagram

```mermaid
classDiagram
    class Database {
        <<final>>
        -string host
        -string dbName
        -string username
        -string password
        -string charset
        -?PDO connection
        +__construct(string host, string dbName, string username, string password, string charset)
        +getConnection() PDO
    }

    class CrudRepository {
        <<interface>>
        +findAll()* array
        +findById(int id)* ?array
        +create(array data)* int
        +update(int id, array data)* bool
        +delete(int id)* bool
    }

    class BaseRepository {
        <<abstract>>
        #PDO db
        +__construct(PDO db)
        #getTableName()* string
        #getPrimaryKey()* string
        #getAllowedColumns()* array
        #getRequiredColumns()* array
        #run(string sql, array params) PDOStatement
        #filterPayload(array input) array
        +findAll() array
        +findById(int id) ?array
        +create(array data) int
        +update(int id, array data) bool
        +delete(int id) bool
    }

    class Category {
        <<final>>
        #getTableName() string
        #getPrimaryKey() string
        #getAllowedColumns() array
        #getRequiredColumns() array
        +findAll() array
    }

    class Equipment {
        <<final>>
        +ALLOWED_CONDITIONS : array
        #getTableName() string
        #getPrimaryKey() string
        #getAllowedColumns() array
        #getRequiredColumns() array
        #filterPayload(array input) array
        +findAll() array
        +findByIdForUpdate(int id) ?array
        +adjustStock(int id, int delta) bool
    }

    class Borrower {
        <<final>>
        #getTableName() string
        #getPrimaryKey() string
        #getAllowedColumns() array
        #getRequiredColumns() array
        #filterPayload(array input) array
        +findAll() array
    }

    class Borrowing {
        <<final>>
        +STATUS_BORROWED : string
        +STATUS_RETURNED : string
        -Equipment equipmentRepository
        +__construct(PDO db, Equipment equipmentRepository)
        #getTableName() string
        #getPrimaryKey() string
        #getAllowedColumns() array
        #getRequiredColumns() array
        #filterPayload(array input) array
        -isValidDate(string date) bool
        +findAll() array
        +create(array data) int
        +update(int id, array data) bool
        +returnEquipment(int id) bool
        +delete(int id) bool
    }

    CrudRepository <|.. BaseRepository
    BaseRepository <|-- Category
    BaseRepository <|-- Equipment
    BaseRepository <|-- Borrower
    BaseRepository <|-- Borrowing
    Borrowing o-- Equipment : equipmentRepository
```

### Alasan Pemilihan Kelas dan Atribut

| Nama Kelas | Alasan Pemilihan Kelas & Atribut |
| :--- | :--- |
| `Database` | Memisahkan konfigurasi dan pembuatan objek koneksi `PDO` (`$host`, `$dbName`, `$username`, `$password`, `$charset`, `$connection`) agar tidak tercampur dengan logika bisnis tabel. |
| `CrudRepository` | Menyediakan kontrak antarmuka standar (`findAll`, `findById`, `create`, `update`, `delete`) sehingga semua repository memiliki perilaku dasar yang seragam. |
| `BaseRepository` | Menghilangkan duplikasi kode CRUD pada setiap tabel. Menyimpan referensi `$db` (`PDO`) dan mengeksekusi seluruh query hanya melalui method `#run()` yang selalu memakai `prepare()` dan `execute()`, serta memvalidasi kolom lewat whitelist `#filterPayload()`. |
| `Category` | Mengontrol tabel `categories` dengan mendefinisikan whitelist kolom (`category_code`, `category_name`, `lab_room`, `description`) serta meng-override `findAll()` untuk menghitung jumlah jenis alat per kategori. |
| `Equipment` | Mengontrol tabel `equipments` dengan atribut kondisi yang diizinkan (`ALLOWED_CONDITIONS`), melakukan `INNER JOIN` ke `categories` pada `findAll()`, serta menyediakan `findByIdForUpdate()` dan `adjustStock()` untuk manipulasi stok yang aman dari balapan data (*race condition*). |
| `Borrower` | Mengontrol tabel `borrowers` dengan validasi format email serta menampilkan jumlah pinjaman aktif setiap peminjam. |
| `Borrowing` | Mengontrol tabel `borrowings` dan beragregasi dengan `Equipment` (`$equipmentRepository`) yang di-inject lewat constructor untuk mengelola transaksi peminjaman, pengembalian (`returnEquipment`), dan pemulihan stok dalam satu transaksi database. |

### Penerapan Prinsip SOLID

1. **Single Responsibility Principle (S)**:
   - `Database` hanya mengurus koneksi PDO.
   - `BaseRepository` hanya mengurus operasi CRUD generik dan prepared statement.
   - Class entitas (`Category`, `Equipment`, `Borrower`, `Borrowing`) masing-masing hanya mengurus aturan bisnis dan query spesifik tabelnya.
   - File di `view/` hanya mengurus tampilan HTML dan interaksi request/response.
2. **Open/Closed Principle (O)**:
   - Menambah entitas/tabel baru cukup dengan membuat class baru yang `extends BaseRepository` dan mendefinisikan nama tabel serta daftar kolom tanpa perlu mengubah kode di `BaseRepository`.
3. **Liskov Substitution Principle (L)**:
   - Seluruh subclass (`Category`, `Equipment`, `Borrower`, `Borrowing`) mematuhi kontrak signature parameter dan return type dari `CrudRepository` maupun `BaseRepository`.
4. **Interface Segregation Principle (I)**:
   - `CrudRepository` dirancang ringkas dan hanya memuat 5 operasi inti CRUD yang memang dibutuhkan oleh seluruh entitas.
5. **Dependency Inversion Principle (D)**:
   - Class repository tidak melakukan `new Database()` atau `new Equipment()` sendiri di dalam method-nya. Objek `PDO` dan `Equipment` di-inject dari luar (`index.php`) melalui constructor.

---

### Sequence Diagram

```mermaid
sequenceDiagram
    actor User as User (Browser)
    participant Index as index.php
    participant View as view/borrowings.php
    participant Borrowing as class/Borrowing.php
    participant Equipment as class/Equipment.php
    participant PDO as PDO (Database)

    User->>Index: Akses halaman / Submit Form POST (index.php?page=borrowings)
    Index->>Index: Validasi whitelist halaman & Inisialisasi PDO + Repository
    Index->>View: require view/borrowings.php

    alt Request adalah POST (Contoh: Catat Peminjaman Baru)
        View->>Borrowing: create($_POST)
        Borrowing->>Borrowing: filterPayload($_POST) (Whitelist & Validasi)
        Borrowing->>PDO: beginTransaction()
        Borrowing->>Equipment: findByIdForUpdate(equipment_id)
        Equipment->>PDO: prepare("SELECT ... FOR UPDATE") & execute()
        PDO-->>Equipment: Data baris alat terkunci
        Equipment-->>Borrowing: Data alat & stok saat ini
        Borrowing->>Equipment: adjustStock(equipment_id, -quantity)
        Equipment->>PDO: prepare("UPDATE equipments SET stock = ...") & execute()
        Borrowing->>PDO: prepare("INSERT INTO borrowings ...") & execute()
        Borrowing->>PDO: commit()
        Borrowing-->>View: ID Peminjaman Baru
        View->>View: setFlash('success', ...) & redirectToPage('borrowings')
        View-->>User: HTTP 302 Redirect ke index.php?page=borrowings (Pola PRG)
    end

    User->>Index: GET index.php?page=borrowings
    Index->>View: require view/borrowings.php
    View->>Borrowing: findAll()
    Borrowing->>PDO: prepare("SELECT ... INNER JOIN ...") & execute()
    PDO-->>Borrowing: Array data peminjaman + nama alat + nama peminjam
    Borrowing-->>View: Return list transaksi
    View-->>User: Render tabel HTML (escaped dengan htmlspecialchars)
```

## Dokumentasi

> **TODO**: Letakkan file video screen record atau gif demonstrasi aplikasi di dalam folder `docs/` (misalnya `docs/demo.mp4` atau `docs/demo.gif`), lalu tautkan di bawah ini sebelum dikumpulkan.

