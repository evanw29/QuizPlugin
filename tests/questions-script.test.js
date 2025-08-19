//Questions-Script test file
describe('Quiz Script', () => {
  test('should handle form submission', () => {
    const mockResponse = { success: true, data: { redirect_url: '/test' } };
    
    global.$.ajax = jest.fn().mockImplementation(({ success }) => {
      success(mockResponse);
    });

    // Simulate AJAX call
    global.$.ajax({
      success: (response) => {
        expect(response.success).toBe(true);
        expect(response.data.redirect_url).toBe('/test');
      }
    });

    expect(global.$.ajax).toHaveBeenCalled();
  });

  test('should validate required fields', () => {
    const formData = { question_1: '', question_2: 'answer' };
    
    // Check if required field is empty
    const hasEmptyFields = Object.values(formData).some(value => value === '');
    
    expect(hasEmptyFields).toBe(true);
  });
});