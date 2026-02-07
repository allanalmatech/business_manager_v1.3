-- Add brands table
CREATE TABLE IF NOT EXISTS brands (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_slug (slug),
    UNIQUE KEY unique_name (name)
);

-- Add brand_id to products table
ALTER TABLE products 
ADD COLUMN brand_id INT NULL AFTER category_id,
ADD FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE SET NULL;

-- Create index for better performance
CREATE INDEX idx_products_brand_id ON products(brand_id);

-- Insert some default brands
INSERT INTO brands (name, slug, description, status) VALUES
('Apple', 'apple', 'Apple Inc. products and accessories', 'active'),
('Samsung', 'samsung', 'Samsung Electronics products', 'active'),
('Sony', 'sony', 'Sony Corporation electronics and entertainment', 'active'),
('LG', 'lg', 'LG Electronics home appliances and electronics', 'active'),
('Microsoft', 'microsoft', 'Microsoft software and hardware', 'active'),
('Dell', 'dell', 'Dell computers and technology solutions', 'active'),
('HP', 'hp', 'HP computers and printing solutions', 'active'),
('Canon', 'canon', 'Canon cameras and imaging products', 'active'),
('Nike', 'nike', 'Nike sports apparel and footwear', 'active'),
('Adidas', 'adidas', 'Adidas sports clothing and accessories', 'active');
