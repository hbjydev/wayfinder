import { readFileSync } from "fs";
import { join } from "path";
import { describe, expect, test } from "vitest";

describe("PaginatedProductController", () => {
    const typesPath = join(
        __dirname,
        "../workbench/resources/js/wayfinder/types.d.ts",
    );

    const types = () => readFileSync(typesPath, "utf-8");

    test("Index emits LengthAwarePaginator with generic product type", () => {
        expect(types()).toContain(
            "products: Illuminate.Pagination.LengthAwarePaginator<App.Models.Product>",
        );
        expect(types()).not.toContain(
            "products: Illuminate.Pagination.LengthAwarePaginator }",
        );
    });

    test("Simple emits Paginator with generic product type", () => {
        expect(types()).toContain(
            "products: Illuminate.Pagination.Paginator<App.Models.Product>",
        );
    });

    test("Cursor emits CursorPaginator with generic product type", () => {
        expect(types()).toContain(
            "products: Illuminate.Pagination.CursorPaginator<App.Models.Product>",
        );
    });

    test("LengthAwarePaginator generic type definition is generated", () => {
        expect(types()).toContain(
            "export type LengthAwarePaginator<T> = { current_page: number, data: T[]",
        );
    });

    test("Paginator generic type definition is generated", () => {
        expect(types()).toContain(
            "export type Paginator<T> = { current_page: number",
        );
    });

    test("CursorPaginator generic type definition is generated", () => {
        expect(types()).toContain(
            "export type CursorPaginator<T> = { data: T[]",
        );
    });
});
