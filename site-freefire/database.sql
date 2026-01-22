-- Tabela de Produtos
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    image_url VARCHAR(255),
    status VARCHAR(20) DEFAULT 'Ativo', -- 'Ativo', 'Manutencao', etc.
    features TEXT -- JSON ou lista separada por pipe
);

-- Tabela de Planos
CREATE TABLE IF NOT EXISTS plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    name VARCHAR(50) NOT NULL, -- Diário, Semanal, etc.
    price DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Inserção de Dados Iniciais

-- 1. Free Fire
INSERT INTO products (slug, name, description, image_url, status, features) VALUES 
('freefire', 'FREE FIRE', 'Bluestacks/MSI 4/5 (Todas as versões). Funções como Chams (O inimigo fica vermelho atrás da parede e verde quando está visível), Aimbot para mira branca e 4x, 2x inclusas. Também incluso CameraHack, No Recoil, AimFov, DoubleGun, FakeDamage entre outras...', '/img/freefire.jpg', 'Ativo', 'Chams|Aimbot|CameraHack|No Recoil|AimFov|DoubleGun|FakeDamage')
ON DUPLICATE KEY UPDATE name=name;

SET @ff_id = (SELECT id FROM products WHERE slug = 'freefire');
INSERT INTO plans (product_id, name, price) VALUES 
(@ff_id, 'Diário', 15.00),
(@ff_id, 'Semanal', 30.00),
(@ff_id, 'Mensal', 60.00),
(@ff_id, 'Permanente', 160.00);


-- 2. Valorant
INSERT INTO products (slug, name, description, image_url, status, features) VALUES 
('valorant', 'VALORANT', 'O monitor é com HGV ON/OFF, não causa lag nem ban, totalmente indetectável pelo vanguard. Funções avançadas de visualização e assistência de mira.', '/img/valorantwall.jpg', 'Ativo', 'HGV ON/OFF|Indetectável|No Lag|No Ban')
ON DUPLICATE KEY UPDATE name=name;

SET @val_id = (SELECT id FROM products WHERE slug = 'valorant');
INSERT INTO plans (product_id, name, price) VALUES 
(@val_id, 'Diário', 20.00),
(@val_id, 'Semanal', 50.00),
(@val_id, 'Mensal', 90.00),
(@val_id, 'Permanente', 250.00);


-- 3. Counter Strike 2
INSERT INTO products (slug, name, description, image_url, status, features) VALUES 
('cs2', 'COUNTER STRIKE 2', 'Domine o CS2 com nosso software premium. Wallhack, Aimbot, Triggerbot e muito mais. Seguro e atualizado constantemente.', '/img/counterstrike2.png', 'Ativo', 'Wallhack|Aimbot|Triggerbot|Skin Changer|Radar Hack')
ON DUPLICATE KEY UPDATE name=name;

SET @cs2_id = (SELECT id FROM products WHERE slug = 'cs2');
INSERT INTO plans (product_id, name, price) VALUES 
(@cs2_id, 'Diário', 15.00),
(@cs2_id, 'Semanal', 35.00),
(@cs2_id, 'Mensal', 70.00);


-- 4. Fortnite
INSERT INTO products (slug, name, description, image_url, status, features) VALUES 
('fortnite', 'FORTNITE', 'Construa e elimine com facilidade. Aimbot preciso, ESP completo e recursos exclusivos para garantir sua Vitória Royale.', '/img/fortnite.png', 'Ativo', 'Aimbot|ESP|Item ESP|No Recoil')
ON DUPLICATE KEY UPDATE name=name;

SET @fn_id = (SELECT id FROM products WHERE slug = 'fortnite');
INSERT INTO plans (product_id, name, price) VALUES 
(@fn_id, 'Diário', 18.00),
(@fn_id, 'Semanal', 45.00),
(@fn_id, 'Mensal', 85.00);


-- 5. Call of Duty
INSERT INTO products (slug, name, description, image_url, status, features) VALUES 
('cod', 'CALL OF DUTY', 'Warzone e Multiplayer. Domine o campo de batalha com ESP avançado e Aimbot humanizado.', '/img/Call of Duty.jpg', 'Ativo', 'UAV Constante|Aimbot|ESP Players|ESP Loot')
ON DUPLICATE KEY UPDATE name=name;

SET @cod_id = (SELECT id FROM products WHERE slug = 'cod');
INSERT INTO plans (product_id, name, price) VALUES 
(@cod_id, 'Diário', 20.00),
(@cod_id, 'Semanal', 50.00),
(@cod_id, 'Mensal', 100.00);


-- 6. GTA V (FiveM)
INSERT INTO products (slug, name, description, image_url, status, features) VALUES 
('fivem', 'GTA V (FIVEM)', 'Melhor menu para RP e PvP. Executor Lua, Aimbot, ESP, God Mode e muito mais para FiveM.', '/img/fivem.png', 'Ativo', 'Lua Executor|Aimbot|ESP|God Mode|Noclip')
ON DUPLICATE KEY UPDATE name=name;

SET @fivem_id = (SELECT id FROM products WHERE slug = 'fivem');
INSERT INTO plans (product_id, name, price) VALUES 
(@fivem_id, 'Semanal', 40.00),
(@fivem_id, 'Mensal', 80.00),
(@fivem_id, 'Lifetime', 200.00);


-- 7. Marvel Rivals
INSERT INTO products (slug, name, description, image_url, status, features) VALUES 
('marvel', 'MARVEL RIVALS', 'Domine seus heróis favoritos com assistência de mira e percepção extra-sensorial.', '/img/marvel.jpg', 'Ativo', 'Aimbot|ESP Herois|Skill Assist')
ON DUPLICATE KEY UPDATE name=name;

SET @marvel_id = (SELECT id FROM products WHERE slug = 'marvel');
INSERT INTO plans (product_id, name, price) VALUES 
(@marvel_id, 'Diário', 15.00),
(@marvel_id, 'Semanal', 35.00),
(@marvel_id, 'Mensal', 70.00);
