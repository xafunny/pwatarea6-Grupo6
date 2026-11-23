-- ============================================================
-- Ébano — Cocina de autor · Base de datos del sistema de restaurante
-- Importable en phpMyAdmin (local o InfinityFree).
-- No lleva CREATE DATABASE ni USE: la BD se crea/selecciona aparte
-- (InfinityFree no permite esas sentencias).
-- Orden: 1) DDL literal del enunciado  2) extensiones del grupo  3) datos de prueba
-- ============================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- Se borran en orden inverso a las dependencias para poder re-importar el archivo
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS dishes;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;

-- ------------------------------------------------------------
-- TABLAS DEL ENUNCIADO: copiadas sin cambios del enunciado
-- ------------------------------------------------------------
CREATE TABLE roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(30) NOT NULL
);
INSERT INTO roles (name) VALUES ('Administrator'), ('Chef'), ('Waiter'), ('Client');
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL,
  email VARCHAR(100) UNIQUE NOT NULL,
  password VARCHAR(100) NOT NULL,
  role_id INT NOT NULL,
  FOREIGN KEY (role_id) REFERENCES roles(id)
);
CREATE TABLE dishes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL,
  description VARCHAR(255),
  price DECIMAL(10, 2) NOT NULL
);
CREATE TABLE orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  dish_id INT NOT NULL,
  quantity INT NOT NULL,
  status ENUM('Pending', 'Preparing', 'Ready to serve', 'Served') NOT NULL DEFAULT 'Pending',
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (dish_id) REFERENCES dishes(id)
);

-- ------------------------------------------------------------
-- EXTENSIONES DEL GRUPO (solo ALTER TABLE; todas opcionales o con valor por defecto,
-- así cualquier INSERT del enunciado sigue funcionando)
-- ------------------------------------------------------------
-- category: agrupar el menú en Entradas, Principales, Postres y Bebidas
-- image: URL de la foto del plato
-- available: ocultar un plato que ya tiene pedidos (la FK no deja borrarlo)
ALTER TABLE dishes
  ADD COLUMN category VARCHAR(30) DEFAULT 'Principal',
  ADD COLUMN image VARCHAR(255) NULL,
  ADD COLUMN available TINYINT(1) NOT NULL DEFAULT 1;

-- waiter_id: camarero asignado al pedido (si se borra el camarero, queda sin asignar)
-- created_at: ordenar el historial y mostrar la hora del pedido
ALTER TABLE orders
  ADD COLUMN waiter_id INT NULL,
  ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  ADD CONSTRAINT fk_orders_waiter FOREIGN KEY (waiter_id) REFERENCES users(id) ON DELETE SET NULL;

-- ------------------------------------------------------------
-- DATOS DE PRUEBA
-- Todos los usuarios usan la contraseña 123456 (mismo hash bcrypt generado con password_hash).
-- ------------------------------------------------------------
INSERT INTO users (id, username, email, password, role_id) VALUES
(1, 'Administrador Ébano', 'admin@ebano.com',    '$2y$12$gNTEJITwJfhbooQFdE9b5eVdA242qjC3Gndj9SvsrQ1UYgZihV3ZG', 1),
(2, 'Chef Mateo Rivas',    'chef@ebano.com',     '$2y$12$gNTEJITwJfhbooQFdE9b5eVdA242qjC3Gndj9SvsrQ1UYgZihV3ZG', 2),
(3, 'Camila Ortega',       'camarero@ebano.com', '$2y$12$gNTEJITwJfhbooQFdE9b5eVdA242qjC3Gndj9SvsrQ1UYgZihV3ZG', 3),
(4, 'Cliente Demo',        'cliente@ebano.com',  '$2y$12$gNTEJITwJfhbooQFdE9b5eVdA242qjC3Gndj9SvsrQ1UYgZihV3ZG', 4),
(5, 'Lucía Fernández',     'lucia@ejemplo.com',  '$2y$12$gNTEJITwJfhbooQFdE9b5eVdA242qjC3Gndj9SvsrQ1UYgZihV3ZG', 4),
(6, 'Andrés Salazar',      'andres@ejemplo.com', '$2y$12$gNTEJITwJfhbooQFdE9b5eVdA242qjC3Gndj9SvsrQ1UYgZihV3ZG', 4),
(7, 'Valeria Núñez',       'valeria@ejemplo.com','$2y$12$gNTEJITwJfhbooQFdE9b5eVdA242qjC3Gndj9SvsrQ1UYgZihV3ZG', 4),
(8, 'Diego Paredes',       'diego@ejemplo.com',  '$2y$12$gNTEJITwJfhbooQFdE9b5eVdA242qjC3Gndj9SvsrQ1UYgZihV3ZG', 4),
(9, 'Sofía Barrera',       'sofia@ejemplo.com',  '$2y$12$gNTEJITwJfhbooQFdE9b5eVdA242qjC3Gndj9SvsrQ1UYgZihV3ZG', 4);

-- 12 platos en 4 categorías. Fotos de Unsplash por URL.
INSERT INTO dishes (id, name, description, price, category, image) VALUES
(1,  'Ensalada de burrata y tomate ancestral', 'Burrata cremosa, tomates de herencia, albahaca fresca y reducción de balsámico añejo.', 12.50, 'Entradas',    'https://images.unsplash.com/photo-1540189549336-e6e99c3679fe?w=800&q=70&auto=format&fit=crop'),
(2,  'Bowl de quinoa y vegetales asados',      'Quinoa tricolor, calabaza asada, aguacate, semillas tostadas y aderezo de tahini.',    9.80, 'Entradas',    'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=800&q=70&auto=format&fit=crop'),
(3,  'Tabla de autor del chef',                'Selección de bocados fríos y calientes de temporada, pan de masa madre y salsas de la casa.', 14.00, 'Entradas', 'https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=800&q=70&auto=format&fit=crop'),
(4,  'Lomo fino con reducción de vino tinto',  'Corte de res madurado, puré de papa trufado, espárragos y jugo de vino tinto.',        27.00, 'Principales', 'https://images.unsplash.com/photo-1529692236671-f1f6cf9683ba?w=800&q=70&auto=format&fit=crop'),
(5,  'Salmón glaseado con cítricos',           'Lomo de salmón sellado, glaseado de naranja y jengibre, vegetales de estación.',       22.50, 'Principales', 'https://images.unsplash.com/photo-1467003909585-2f8a72700288?w=800&q=70&auto=format&fit=crop'),
(6,  'Tagliatelle al ragú blanco',             'Pasta fresca artesanal, ragú blanco de cordero lento y lluvia de parmesano curado.',   18.00, 'Principales', 'https://images.unsplash.com/photo-1551183053-bf91a1d81141?w=800&q=70&auto=format&fit=crop'),
(7,  'Brochetas de la parrilla Ébano',         'Brochetas de res y vegetales a las brasas con chimichurri ahumado y papas rústicas.',  16.50, 'Principales', 'https://images.unsplash.com/photo-1555939594-58d7cb561ad1?w=800&q=70&auto=format&fit=crop'),
(8,  'Esfera de chocolate y frutos rojos',     'Chocolate 70% con corazón de frambuesa, crumble de cacao y helado de vainilla.',        8.50, 'Postres',     'https://images.unsplash.com/photo-1624353365286-3f8d62daad51?w=800&q=70&auto=format&fit=crop'),
(9,  'Tarta cremosa de temporada',             'Base crujiente de mantequilla, crema ligera y fruta fresca de la estación.',             7.50, 'Postres',     'https://images.unsplash.com/photo-1519915028121-7d3463d20b13?w=800&q=70&auto=format&fit=crop'),
(10, 'Helado artesanal de la casa',            'Tres bolas de helado elaborado a diario, con salsa tibia y galleta de almendra.',        6.00, 'Postres',     'https://images.unsplash.com/photo-1563805042-7684c019e1cb?w=800&q=70&auto=format&fit=crop'),
(11, 'Cóctel de autor Ébano',                  'Ron añejo, cítricos, jarabe de especias y un toque de amargo aromático.',               11.00, 'Bebidas',     'https://images.unsplash.com/photo-1470337458703-46ad1756a187?w=800&q=70&auto=format&fit=crop'),
(12, 'Spritz de hierbas y flor de saúco',      'Burbujas ligeras con licor de saúco, hierbas frescas y rodaja de limón.',                9.00, 'Bebidas',     'https://images.unsplash.com/photo-1551538827-9c037cb4f32a?w=800&q=70&auto=format&fit=crop');

-- 15 pedidos en todos los estados, repartidos en las últimas horas.
-- waiter_id NULL = pedido sin asignar (lo puede tomar un camarero).
INSERT INTO orders (user_id, dish_id, quantity, status, waiter_id, created_at) VALUES
(4, 4,  1, 'Pending',        NULL, DATE_SUB(NOW(), INTERVAL 5 MINUTE)),
(5, 1,  2, 'Pending',        NULL, DATE_SUB(NOW(), INTERVAL 12 MINUTE)),
(6, 6,  1, 'Pending',        3,    DATE_SUB(NOW(), INTERVAL 18 MINUTE)),
(7, 11, 2, 'Pending',        NULL, DATE_SUB(NOW(), INTERVAL 25 MINUTE)),
(4, 5,  1, 'Preparing',      3,    DATE_SUB(NOW(), INTERVAL 35 MINUTE)),
(8, 7,  2, 'Preparing',      NULL, DATE_SUB(NOW(), INTERVAL 45 MINUTE)),
(9, 3,  1, 'Preparing',      3,    DATE_SUB(NOW(), INTERVAL 55 MINUTE)),
(5, 4,  1, 'Ready to serve', 3,    DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(6, 2,  2, 'Ready to serve', NULL, DATE_SUB(NOW(), INTERVAL 75 MINUTE)),
(7, 8,  1, 'Ready to serve', 3,    DATE_SUB(NOW(), INTERVAL 90 MINUTE)),
(4, 6,  2, 'Served',         3,    DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(8, 12, 1, 'Served',         3,    DATE_SUB(NOW(), INTERVAL 3 HOUR)),
(9, 9,  2, 'Served',         3,    DATE_SUB(NOW(), INTERVAL 4 HOUR)),
(5, 10, 1, 'Served',         NULL, DATE_SUB(NOW(), INTERVAL 5 HOUR)),
(6, 5,  1, 'Served',         3,    DATE_SUB(NOW(), INTERVAL 6 HOUR));

SET FOREIGN_KEY_CHECKS=1;
