CREATE TABLE IF NOT EXISTS tours (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    slug         TEXT NOT NULL UNIQUE,
    title        TEXT NOT NULL,
    city         TEXT NOT NULL,
    description  TEXT NOT NULL,
    duration_days INTEGER NOT NULL CHECK (duration_days > 0),
    adult_price  INTEGER NOT NULL CHECK (adult_price >= 0),   -- kuruş değil, TL cinsinden tam sayı
    child_price  INTEGER NOT NULL CHECK (child_price >= 0)
);

-- Her tur için belirli kalkış tarihleri ve o tarihin kontenjanı
CREATE TABLE IF NOT EXISTS departures (
    id        INTEGER PRIMARY KEY AUTOINCREMENT,
    tour_id   INTEGER NOT NULL REFERENCES tours(id) ON DELETE CASCADE,
    date      TEXT NOT NULL,
    capacity  INTEGER NOT NULL CHECK (capacity > 0),
    UNIQUE (tour_id, date)
);

CREATE TABLE IF NOT EXISTS reservations (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    code          TEXT NOT NULL UNIQUE,
    departure_id  INTEGER NOT NULL REFERENCES departures(id),
    adults        INTEGER NOT NULL CHECK (adults >= 1),
    children      INTEGER NOT NULL DEFAULT 0 CHECK (children >= 0),
    total_price   INTEGER NOT NULL,
    customer_name  TEXT NOT NULL,
    customer_email TEXT NOT NULL,
    customer_phone TEXT NOT NULL,
    status        TEXT NOT NULL DEFAULT 'confirmed' CHECK (status IN ('confirmed','cancelled')),
    created_at    TEXT NOT NULL,
    cancelled_at  TEXT
);

CREATE INDEX IF NOT EXISTS idx_res_departure ON reservations(departure_id, status);
