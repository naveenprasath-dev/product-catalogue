import type { Category, CategoryProducts, Product, User } from '../api/types'

export const ada: User = { id: 7, name: 'Ada Lovelace', email: 'ada@example.com' }

export const mensClothing: Category = { slug: 'mens-clothing', name: "men's clothing" }
export const electronics: Category = { slug: 'electronics', name: 'electronics' }
export const categories: Category[] = [electronics, mensClothing]

let nextId = 1

export function makeProduct(overrides: Partial<Product> = {}): Product {
  const id = overrides.id ?? nextId++
  return {
    id,
    title: `Product ${id}`,
    description: `Description of product ${id}`,
    price: 19.99,
    image_url: `https://images.example.test/${id}.jpg`,
    category: mensClothing,
    rating: { rate: 4.1, count: 120 },
    ...overrides,
  }
}

export const backpack = makeProduct({
  id: 1,
  title: 'Fjallraven Foldsack No. 1 Backpack',
  description: 'Your perfect pack for everyday use and walks in the forest.',
  price: 109.95,
  rating: { rate: 3.9, count: 120 },
})
export const jacket = makeProduct({
  id: 3,
  title: 'Mens Cotton Jacket',
  description: 'Great outerwear jackets for Spring/Autumn/Winter.',
  price: 55.99,
  rating: { rate: 4.7, count: 500 },
})
export const tshirt = makeProduct({ id: 2, title: 'Mens Casual Premium Slim Fit T-Shirts', rating: { rate: 4.1, count: 259 } })
export const shirt = makeProduct({ id: 4, title: 'Mens Casual Slim Fit', rating: { rate: 2.1, count: 430 } })
export const harddrive = makeProduct({
  id: 9,
  title: 'WD 2TB Elements Portable External Hard Drive',
  category: electronics,
  price: 64,
  rating: { rate: 3.3, count: 203 },
})

export const products: Product[] = [backpack, tshirt, jacket, shirt, harddrive]
export const featured: Product[] = [jacket, tshirt, backpack, harddrive, shirt]

export const mensClothingListing: CategoryProducts = {
  category: mensClothing,
  top_rated: [jacket, tshirt, backpack],
  others: [shirt],
}
