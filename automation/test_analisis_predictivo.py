# Guardar como automation/test_analisis_predictivo.py
import os
import time
from selenium import webdriver
from selenium.webdriver.common.by import By

def test_modulo_prediccion_riesgo():
    driver = webdriver.Chrome()
    driver.implicitly_wait(10)
    
    try:
        # Encontrar la ruta real del archivo index.html simulación
        ruta_actual = os.path.abspath(os.path.dirname(__file__))
        ruta_html = os.path.join(ruta_actual, "index.html")
        
        # Selenium abre el clon local de tu Moodle
        driver.get(f"file:///{ruta_html}")
        time.sleep(2)
        
        # 1. Verificar estado inicial
        assert "Sin evaluar" in driver.page_source, "El estado inicial debería ser 'Sin evaluar'"
        print("Estado inicial verificado: Alumnos sin evaluar. ✅")
        
        # 2. Hacer clic en Ejecutar IA
        boton = driver.find_element(By.ID, "btn-ejecutar")
        boton.click()
        print("Botón 'Ejecutar IA' presionado. Procesando algoritmo predictivo...")
        
        # 3. Esperar que el script modifique el HTML simulando el cálculo de la IA
        time.sleep(4)
        
        # 4. Verificar que se calcularon los riesgos de deserción
        riesgo_juan = driver.find_element(By.ID, "riesgo-1").text
        assert "Alto Riesgo" in riesgo_juan, "Error: La IA no predijo el riesgo esperado para Juan."
        
        print(f"¡Resultados de la predicción de IA validados! Juan Pérez: {riesgo_juan} ✅")
        print("¡Prueba de TMMi Nivel 5 completada exitosamente de forma continua! 🚀")
        
    except Exception as e:
        print(f"La prueba de automatización falló: {e} ❌")
        raise e
    finally:
        driver.quit()

if __name__ == "__main__":
    test_modulo_prediccion_riesgo()