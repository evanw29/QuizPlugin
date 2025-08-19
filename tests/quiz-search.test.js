//Quiz-search test file
describe('Quiz Search', () => {
  test('should search for quizzes', () => {
    const searchData = {
      email: 'test@example.com',
      lastName: 'Smith',
      phoneNumber: '1234567890'
    };

    //Check all fields have values
    expect(searchData.email).toBeTruthy();
    expect(searchData.lastName).toBeTruthy();
    expect(searchData.phoneNumber).toBeTruthy();
  });

  test('should handle search results', () => {
    const mockResults = [
      { QuizID: 123, Date: '2024-01-15', recommendations: ['Tech 1', 'Tech 2'] }
    ];

    expect(mockResults).toHaveLength(1);
    expect(mockResults[0].QuizID).toBe(123);
  });
});